<?php

namespace App\Http\Controllers\Api\Content;

use App\Core\Content\LegalPage;
use App\Http\Controllers\Controller;

class LegalController extends Controller
{
    public function show(string $slug)
    {
        if (! array_key_exists($slug, LegalPage::SLUGS)) {
            return response()->json(['error' => 'not found'], 404);
        }

        $page = LegalPage::resolve($slug, app()->getLocale());
        if (! $page) {
            // No placeholder legal text is ever invented: the operator must publish it.
            return response()->json(['published' => false, 'slug' => $slug, 'title' => trans(LegalPage::SLUGS[$slug])], 200);
        }

        return response()->json([
            'published'  => true,
            'slug'       => $slug,
            'locale'     => $page->locale,
            'title'      => $page->title,
            'html'       => $page->html(),
            'updated_at' => $page->updated_at?->toIso8601String(),
        ]);
    }
}
