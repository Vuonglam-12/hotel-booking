<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\Itinerary\PlannerRequest;
use App\Http\Resources\ItineraryTripResource;
use App\Services\Itinerary\ConversationalPlanner;
use App\Services\Itinerary\IdempotentWriter;
use App\Services\Itinerary\PlannerService;
use App\Services\Itinerary\TripService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

final class ItineraryPlannerController extends Controller
{
    public function placePhoto(Request $request, \App\Services\Itinerary\PlannerPhotos $photos, \App\Services\Itinerary\PlacePhoto $images, TripService $trips)
    {
        $data=$request->validate([
            'itinerary_id'=>'required_without:photo_reference|prohibits:photo_reference|integer|min:1',
            'item_id'=>'required_with:itinerary_id|integer|min:1',
            'photo_reference'=>'required_without:itinerary_id|array:message_id,day_number,item_index',
            'photo_reference.message_id'=>'required_with:photo_reference|integer|min:1',
            'photo_reference.day_number'=>'required_with:photo_reference|integer|min:1|max:30',
            'photo_reference.item_index'=>'required_with:photo_reference|integer|min:0|max:49',
            'title'=>'required|string|max:200','place_name'=>'nullable|string|max:500',
        ]);
        if (!empty($data['photo_reference'])) {
            $source=$photos->proposal($request->user(),$data['photo_reference']);
            abort_unless(($source['title'] ?? '')===$data['title'] && trim($source['address'] ?? '')===trim($data['place_name'] ?? ''),422,'Địa điểm đã thay đổi.');
            $place=$photos->place($source);
        } else {
            $trip=$trips->owned($request->user(),$data['itinerary_id']);
            $item=\Illuminate\Support\Facades\DB::table('itinerary_item')->where('itinerary_id',$trip->id)->find($data['item_id']);abort_unless($item,404);
            $json=\Illuminate\Support\Facades\DB::table('itinerary_place_snapshot')->where('itinerary_id',$trip->id)->where('id',$item->place_snapshot_id)->value('snapshot');
            $snapshot=json_decode($json ?? '{}',true);
            $placeName=$snapshot['manual_place_name'] ?? $snapshot['name'] ?? $snapshot['address'] ?? '';
            abort_unless($item->title===$data['title'] && trim($placeName)===trim($data['place_name'] ?? '') && $photos->matches($snapshot,$item->title,$placeName),422,'Địa điểm đã thay đổi.');
            $place=$photos->place($snapshot['photo_place'] ?? $snapshot);
        }
        // This optional lookup only fills the photo cache. It never edits the itinerary or its revision.
        $image=$photos->eligible($place)?$images->forPlace($place):null;
        return response()->json(['image'=>$image]);
    }

    public function checkHours(Request $request, \App\Services\Places\ItineraryHours $hours)
    {
        $data=$request->validate(PlannerRequest::tripRules());
        return response()->json($hours->checkPayload($data,$request->user()));
    }

    public function placeHours(Request $request,int $id,\App\Services\Places\OpeningHours $hours)
    {
        $data=$request->validate(['date'=>'nullable|date_format:Y-m-d']);
        $place=\Illuminate\Support\Facades\DB::table('travel_source_place')->where('is_enabled',true)->find($id);
        abort_unless($place && $place->source===\App\Services\Places\SheetPlaceSync::source((int)$place->location_id),404);
        $check=$hours->check(!empty($place->opening_schedule)?json_decode($place->opening_schedule,true):null,$data['date'] ?? null);
        $check['display']=app(\App\Services\Places\ItineraryHours::class)->displayForPlace((array)$place);
        return response()->json(['opening_check'=>$check,'hours_text'=>$place->hours_text]);
    }

    public function write(PlannerRequest $request, PlannerService $planner, IdempotentWriter $writer, ?int $id = null)
    {
        abort_unless(Schema::hasColumn('itinerary', 'is_provisional'), 503, 'Chức năng lưu lịch đang được cập nhật. Vui lòng thử lại sau.');
        $data = $request->validated();
        $operation = 'planner:'.($id ?? 'create');
        $result = $writer->run($request->user(), $data['request_key'], $operation, $data, function () use ($request, $planner, $data, $id) {
            $trip = $id ? $planner->save($request->user(), $id, $data) : $planner->create($request->user(), $data);
            if (! $id) $trip->forceFill(['status' => 'saved'])->save();
            return ['status' => $id ? 200 : 201, 'itinerary_id' => $trip->id, 'body' => ['data' => (new ItineraryTripResource($trip))->resolve($request)]];
        });
        return response()->json($result['body'], $result['status']);
    }

    public function generate(PlannerRequest $request, PlannerService $planner, ConversationalPlanner $generator, IdempotentWriter $writer)
    {
        abort_unless(Schema::hasColumn('itinerary', 'is_provisional'), 503, 'Chức năng lịch trình đang được cập nhật. Vui lòng thử lại sau.');
        $data = $request->validated(); $customer = $request->user(); $key = $data['request_key'];
        $result = $writer->replay($customer, $key, 'planner:generate', $data);
        if (! $result) $result = Cache::lock('planner.generate.'.$customer->id.'.'.hash('sha256', $key), 300)->get(function () use ($data, $customer, $key, $writer, $generator, $planner, $request) {
            if ($previous = $writer->replay($customer, $key, 'planner:generate', $data)) return $previous;
            $plan = $generator->prepare($planner->state($data), ['intent' => 'plan'], null);
            return $writer->run($customer, $key, 'planner:generate', $data, function () use ($planner, $customer, $data, $plan, $request) {
                $trip = $planner->create($customer, $data, $plan);
                return ['status' => 201, 'itinerary_id' => $trip->id, 'body' => ['data' => (new ItineraryTripResource($trip))->resolve($request), 'assumptions' => $plan['assumptions'],'planning_warnings'=>$plan['prepared']['warnings'] ?? []]];
            });
        });
        abort_unless($result, 409, 'AI đang xử lý yêu cầu này. Vui lòng chờ rồi thử lại.');
        return response()->json($result['body'], $result['status']);
    }

    public function pdf(Request $request, int $id, TripService $trips)
    {
        $trip = (new ItineraryTripResource($trips->owned($request->user(), $id)))->resolve($request);
        return Pdf::loadView('pdf.itinerary', ['trip' => $trip])->setPaper('a4')->download('lich-trinh-'.$id.'.pdf');
    }
}
