<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveAttractionRequest;
use App\Services\Admin\AttractionService;
use App\Services\AdminAccommodationImageService;
use App\Support\ExperienceCategory;
use App\Support\ExperienceSchema;
use Illuminate\Http\Request;

final class AttractionController extends Controller
{
    public function __construct(private AttractionService $service, private AdminAccommodationImageService $images) {}

    public function page() { return view('admin.experiences'); }

    public function meta(ExperienceSchema $schema)
    {
        $schema->ensureReady();
        return response()->json(['categories' => collect(ExperienceCategory::LABELS)->map(fn ($label, $key) => [
            'key' => $key, 'label' => $label, 'item_type' => ExperienceCategory::TYPES[$key],
        ])->values(), 'image_limits' => AdminAccommodationImageService::limits()]);
    }

    public function listing(Request $request)
    {
        return response()->json($this->service->listing($request->validate([
            'location_id' => 'nullable|integer|min:1', 'q' => 'nullable|string|max:120',
            'archived' => 'nullable|boolean', 'page' => 'nullable|integer|min:1',
        ])));
    }

    public function index(int $locId) { return response()->json($this->service->getByLocation($locId)); }
    public function show(int $id) { return response()->json($this->service->detail($id)); }

    public function store(SaveAttractionRequest $request, int $locId)
    {
        [$changes, $files] = $this->images->input($request);
        return response()->json($this->service->save(null, [...$request->validated(), 'location_id' => $locId], $changes, $files, $request->user('admin')->id), 201);
    }

    public function update(SaveAttractionRequest $request, int $id)
    {
        [$changes, $files] = $this->images->input($request);
        return response()->json($this->service->save($id, $request->validated(), $changes, $files, $request->user('admin')->id));
    }

    public function destroy(int $id)
    {
        $this->service->archive($id);
        return response()->json(['message' => 'Đã lưu trữ trải nghiệm. Dữ liệu và ảnh được giữ lại.']);
    }

    public function restore(int $id)
    {
        $this->service->restore($id);
        return response()->json(['message' => 'Đã khôi phục dưới dạng nháp. Cần xác minh trước khi xuất bản.']);
    }

    public function toggle(int $id) { return response()->json($this->service->toggle($id)); }
}
