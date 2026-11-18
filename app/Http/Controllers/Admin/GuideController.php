<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveGuideRequest;
use App\Services\Admin\GuideService;
use Illuminate\Http\Request;

final class GuideController extends Controller
{
    public function __construct(private GuideService $service) {}
    public function page() { return view('admin.guides'); }
    public function meta() { return response()->json($this->service->metadata()); }
    public function preview(Request $request, \App\Support\GuideHtml $html)
    {
        $data = $request->validate(['body_html' => 'nullable|string|max:200000']);
        return response()->json(['body_html' => $html->clean($data['body_html'] ?? '')]);
    }
    public function index(Request $request)
    {
        return response()->json($this->service->listing($request->validate([
            'q' => 'nullable|string|max:120', 'status' => 'nullable|in:draft,published', 'category_id' => 'nullable|integer|min:1',
            'archived' => 'nullable|boolean', 'page' => 'nullable|integer|min:1',
        ])));
    }
    public function show(int $id) { return response()->json($this->service->detail($id)); }
    public function store(SaveGuideRequest $request) { return response()->json($this->service->save(null, $request->validated(), $request->file('cover')), 201); }
    public function update(SaveGuideRequest $request, int $id) { return response()->json($this->service->save($id, $request->validated(), $request->file('cover'))); }
    public function destroy(int $id) { $this->service->archive($id); return response()->json(['message' => 'Đã lưu trữ bài viết. Nội dung và ảnh được giữ lại.']); }
    public function restore(int $id) { $this->service->restore($id); return response()->json(['message' => 'Đã khôi phục bản nháp.']); }
    public function storeCategory(Request $request) { return response()->json($this->service->saveCategory(null, $this->categoryInput($request)), 201); }
    public function updateCategory(Request $request, int $id) { return response()->json($this->service->saveCategory($id, $this->categoryInput($request, $id))); }
    public function destroyCategory(int $id) { $this->service->deleteCategory($id); return response()->json(['message' => 'Đã xóa danh mục trống.']); }
    private function categoryInput(Request $request, ?int $id = null): array
    {
        app(\App\Support\GuideSchema::class)->ensureReady();
        return $request->validate(['name' => ['required', 'string', 'max:100', \Illuminate\Validation\Rule::unique('guide_category', 'name')->ignore($id)], 'is_active' => 'required|boolean']);
    }
}
