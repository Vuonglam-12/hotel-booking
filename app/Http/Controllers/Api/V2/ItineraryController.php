<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\Itinerary\DeleteRequest;
use App\Http\Requests\Itinerary\MutationRequest;
use App\Http\Requests\Itinerary\StatusRequest;
use App\Http\Requests\Itinerary\TripRequest;
use App\Http\Resources\ItineraryTripResource;
use App\Models\Itinerary;
use App\Services\Itinerary\DestinationService;
use App\Services\Itinerary\IdempotentWriter;
use App\Services\Itinerary\GenerationService;
use App\Services\Itinerary\TripService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

final class ItineraryController extends Controller
{
    public function __construct(private TripService $trips, private IdempotentWriter $writer) {}

    public function destinations(Request $request, DestinationService $destinations)
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:150']]);

        return response()->json(['data' => $destinations->listing($data['q'] ?? null),
            'place_restrictions'=>array_map(fn($entry)=>array_intersect_key($entry,array_flip(['location_name','names','coordinates','address','provider_place_id','status'])),app(\App\Services\Itinerary\PlaceAvailability::class)->restrictions())]);
    }

    public function index(Request $request)
    {
        $data = $request->validate(['status' => ['sometimes', Rule::in(['draft', 'saved'])], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']]);

        return ItineraryTripResource::collection(Itinerary::where('customer_id', $request->user()->id)->where('schema_version', 2)
            ->when(isset($data['status']), fn ($q) => $q->where('status', $data['status']))->orderByDesc('id')->paginate($data['per_page'] ?? 20));
    }

    public function show(Request $request, int $id)
    {
        return new ItineraryTripResource($this->trips->owned($request->user(), $id));
    }

    public function store(TripRequest $request)
    {
        return $this->write($request, 'create', function (array $data) use ($request) {
            $trip = $this->trips->create($request->user(), $data);

            return ['status' => 201, 'itinerary_id' => $trip->id, 'body' => ['data' => (new ItineraryTripResource($trip))->resolve($request)]];
        });
    }

    public function generate(TripRequest $request, GenerationService $generator)
    {
        $data=$request->validated();$customer=$request->user();$key=$data['request_key'];
        $result=$this->writer->replay($customer,$key,'generate',$data);
        if (!$result) {
            // Do not hold a Customer row lock while external APIs run: booking must remain independent.
            $result=Cache::lock('itinerary.generate.'.$customer->id.'.'.hash('sha256',$key),120)->get(function()use($customer,$key,$data,$generator,$request){
                if($previous=$this->writer->replay($customer,$key,'generate',$data))return $previous;
                $prepared=$generator->prepare($data);
                return $this->writer->run($customer,$key,'generate',$data,function()use($customer,$data,$generator,$prepared,$request){
                    $trip=$this->trips->create($customer,$data);
                    $generator->persist($trip,$prepared);
                    return ['status'=>201,'itinerary_id'=>$trip->id,'body'=>['data'=>(new ItineraryTripResource($trip->refresh()))->resolve($request)]];
                });
            });
            if (!$result) return response()->json(['message'=>'Yêu cầu này đang được xử lý. Vui lòng đợi rồi thử lại.','code'=>'generation_in_progress'],409);
        }
        return response()->json($result['body'],$result['status'])->header('Idempotency-Replayed',$result['replayed']?'true':'false');
    }

    public function update(TripRequest $request, int $id)
    {
        return $this->write($request, 'replace:'.$id, function (array $data) use ($request, $id) {
            $trip = $this->trips->update($request->user(), $id, $data);

            return ['status' => 200, 'itinerary_id' => $id, 'body' => ['data' => (new ItineraryTripResource($trip))->resolve($request)]];
        });
    }

    public function status(StatusRequest $request, int $id)
    {
        return $this->write($request, 'status:'.$id, function (array $data) use ($request, $id) {
            $trip = $this->trips->status($request->user(), $id, $data);

            return ['status' => 200, 'itinerary_id' => $id, 'body' => ['data' => (new ItineraryTripResource($trip))->resolve($request)]];
        });
    }

    public function destroy(DeleteRequest $request, int $id)
    {
        return $this->write($request, 'delete:'.$id, function (array $data) use ($request, $id) {
            $this->trips->delete($request->user(), $id, $data['expected_revision']);

            return ['status' => 204, 'body' => null];
        });
    }

    private function write(MutationRequest $request, string $operation, callable $callback)
    {
        $data = $request->validated();
        $result = $this->writer->run($request->user(), $data['request_key'], $operation, $data, fn () => $callback($data));

        return response()->json($result['body'], $result['status'])->header('Idempotency-Replayed', $result['replayed'] ? 'true' : 'false');
    }
}
