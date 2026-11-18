<?php

namespace App\Http\Controllers;

use App\Services\GuideCatalogService;
use App\Support\GuideSchema;
use App\Support\LegacyBlogCatalog;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

final class BlogController extends Controller
{
    public function index(Request $request)
    {
        return redirect()->route('guides.index', $request->only(['q', 'category', 'page']), 301);
    }

    public function detail(string $id, GuideSchema $schema, GuideCatalogService $catalog)
    {
        abort_unless(ctype_digit($id) && collect(LegacyBlogCatalog::INDEX)->contains('id', (int) $id), 404);
        try {
            if ($schema->ready()) {
                $post = $catalog->publicQuery()->where('legacy_id', (int) $id)->first();
                if ($post) return redirect()->route('guides.show', $post->slug, 302);
            }
        } catch (QueryException $error) { report($error); }
        // A known draft/missing import never redirects visitors into a public 404 or exposes draft content.
        return redirect()->route('guides.index', ['legacy' => (int) $id], 302);
    }
}
