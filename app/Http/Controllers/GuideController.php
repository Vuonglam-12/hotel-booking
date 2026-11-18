<?php

namespace App\Http\Controllers;

use App\Services\GuideCatalogService;
use App\Support\GuideSchema;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

final class GuideController extends Controller
{
    public function __construct(private GuideCatalogService $catalog) {}
    public function index() { return view('guides.index'); }
    public function show(string $slug, GuideSchema $schema)
    {
        try {
            if (!$schema->ready()) return response()->view('guides.unavailable', [], 503);
            return view('guides.show', ['guide' => $this->catalog->present($this->catalog->find($slug), true)]);
        } catch (QueryException $error) { report($error); return response()->view('guides.unavailable', [], 503); }
    }
    public function listing(Request $request)
    {
        $filters = $request->validate(['category' => 'nullable|string|max:140|regex:/^[a-z0-9-]+$/D',
            'q' => 'nullable|string|max:120', 'page' => 'nullable|integer|min:1', 'per_page' => 'nullable|integer|min:1|max:24']);
        try { return response()->json($this->catalog->listing($filters))->header('Cache-Control', 'no-store'); }
        catch (QueryException $error) { report($error); return response()->json(['code' => 'GUIDES_UNAVAILABLE', 'message' => 'Chưa thể kết nối Cẩm nang. Vui lòng thử lại.'], 503)->header('Cache-Control', 'no-store'); }
    }
    public function detail(string $slug)
    {
        try { return response()->json(['data' => $this->catalog->present($this->catalog->find($slug), true)])->header('Cache-Control', 'no-store'); }
        catch (QueryException $error) { report($error); return response()->json(['code' => 'GUIDES_UNAVAILABLE', 'message' => 'Chưa thể kết nối Cẩm nang. Vui lòng thử lại.'], 503)->header('Cache-Control', 'no-store'); }
    }
}
