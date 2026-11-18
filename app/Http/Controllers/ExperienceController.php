<?php

namespace App\Http\Controllers;

use App\Services\ExperienceCatalogService;
use App\Support\ExperienceCategory;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class ExperienceController extends Controller
{
    public function __construct(private ExperienceCatalogService $catalog) {}

    public function index()
    {
        return view('experiences.index', ['categories' => ExperienceCategory::LABELS]);
    }

    public function show(string $slug)
    {
        try {
            $experience = $this->catalog->present($this->catalog->find($slug), true);
            return view('experiences.show', compact('experience'));
        } catch (QueryException $error) {
            report($error);
            return response()->view('experiences.unavailable', [], 503);
        } catch (HttpExceptionInterface $error) {
            if ($error->getStatusCode() !== 503) throw $error;
            return response()->view('experiences.unavailable', [], 503);
        }
    }

    public function apiIndex(Request $request)
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:120', 'location' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D'],
            'category' => ['nullable', Rule::in(array_keys(ExperienceCategory::LABELS))],
            'sort' => ['nullable', Rule::in(['latest', 'name'])],
            'page' => 'nullable|integer|min:1', 'per_page' => 'nullable|integer|min:1|max:24',
        ]);
        return $this->read(fn () => $this->catalog->listing(array_filter($filters, fn ($v) => $v !== null)));
    }

    public function apiFilters()
    {
        return $this->read(fn () => $this->catalog->filters());
    }

    public function apiShow(string $slug)
    {
        return $this->read(fn () => ['data' => $this->catalog->present($this->catalog->find($slug), true)]);
    }

    private function read(callable $callback)
    {
        try { return response()->json($callback())->header('Cache-Control', 'no-store'); }
        catch (QueryException $error) {
            report($error);
            return response()->json(['message' => 'Không tải được trải nghiệm. Vui lòng thử lại sau.'], 503);
        }
    }
}
