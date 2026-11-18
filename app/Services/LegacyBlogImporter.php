<?php

namespace App\Services;

use App\Models\GuideCategory;
use App\Models\GuidePost;
use App\Support\GuideHtml;
use App\Support\GuideSchema;
use App\Support\LegacyBlogCatalog;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class LegacyBlogImporter
{
    public function __construct(private GuideHtml $html, private GuideSchema $schema) {}

    /** Filesystem only. Never renders/evaluates a legacy Blade file or opens a database. */
    public function inspect(): array
    {
        return array_map(function ($metadata) {
            $path = resource_path('views/content/blog-'.$metadata['id'].'-content.blade.php');
            $raw = is_file($path) ? file_get_contents($path) : null;
            $hash = $raw === null ? null : hash('sha256', $raw);
            $body = '';
            // Normalize checkout line endings/BOM for review identity; archive/hash the original bytes unchanged.
            $normalized = str_replace(["\r\n", "\r"], "\n", preg_replace('/^\xEF\xBB\xBF/', '', $raw ?? ''));
            // This is the only existing file whose complete editorial boundaries were reviewed.
            $complete = $metadata['id'] === 1 && hash('sha256', $normalized) === 'f1edc35e41bb7879a5f307215fb9d1b28a536baa388e245dab2d696dcf8645a7';
            if ($complete) {
                $fragment = explode('<!-- KHUNG COMMENT -->', $raw, 2)[0];
                $document = new DOMDocument('1.0', 'UTF-8');
                $previous = libxml_use_internal_errors(true);
                try {
                    $document->loadHTML('<?xml encoding="UTF-8">'.$fragment, LIBXML_NONET);
                    $xpath = new DOMXPath($document);
                    foreach ($xpath->query('//h1 | //div[contains(concat(" ", normalize-space(@class), " "), " audio-section ")]') as $node) $node->parentNode->removeChild($node);
                    $body = $this->html->clean($document->saveHTML());
                } finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
                $complete = mb_strlen($this->html->text($body)) > 2000 && substr_count($body, '<h2>') >= 4;
            }
            return ['metadata' => $metadata, 'raw' => $raw, 'hash' => $hash, 'body' => $body,
                'state' => $complete ? 'complete' : ($raw === null ? 'missing' : (trim($raw) === '' ? 'empty' : 'needs_review')),
                'cover_exists' => is_file(public_path('image/'.$metadata['image']))];
        }, LegacyBlogCatalog::INDEX);
    }

    /** Idempotent insert only: never overwrite an edited or previously imported post. */
    public function apply(): array
    {
        $this->schema->ensureReady();
        $rows = $this->inspect();
        return DB::transaction(function () use ($rows) {
            $report = [];
            foreach ($rows as $row) {
                $meta = $row['metadata'];
                if ($existing = GuidePost::where('legacy_id', $meta['id'])->first()) {
                    $report[] = ['id' => $meta['id'], 'result' => 'kept', 'status' => $existing->status];
                    continue;
                }
                $category = GuideCategory::firstOrCreate(['slug' => Str::slug($meta['tag'])], ['name' => $meta['tag'], 'is_active' => true]);
                // Content completeness is not editorial approval. Only Admin may publish later.
                $post = new GuidePost(['slug' => Str::limit(Str::slug($meta['title']), 180, '').'-blog-'.$meta['id'],
                    'title' => $meta['title'], 'excerpt' => $row['body'] ? Str::limit($this->html->text($row['body']), 260) : null,
                    'body_html' => $row['body'] ?: null, 'cover_path' => 'image/'.$meta['image'],
                    'category_id' => $category->id, 'author_name' => null, 'location_id' => null,
                    'status' => 'draft', 'is_featured' => false, 'published_at' => null]);
                $post->legacy_id = $meta['id'];
                $post->legacy_metadata = ['index' => $meta, 'detail' => collect(LegacyBlogCatalog::DETAIL)->firstWhere('id', $meta['id']),
                    'content_file' => 'resources/views/content/blog-'.$meta['id'].'-content.blade.php', 'cover_existed_at_import' => $row['cover_exists']];
                $post->legacy_body = $row['raw']; $post->legacy_content_sha256 = $row['hash']; $post->legacy_import_state = $row['state'];
                $post->save();
                $report[] = ['id' => $meta['id'], 'result' => 'imported', 'status' => $post->status];
            }
            return $report;
        });
    }
}
