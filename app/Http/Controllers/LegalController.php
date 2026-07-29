<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Privacy and terms. Content lives in lang/{en,es,pt}/legal.php — the same
 * pattern the help center uses, because whole paragraphs do not belong in the
 * string-by-string JSON map the generator builds.
 *
 * The operator and contact of THIS instance come from config (env-backed) so a
 * self-hoster does not publish the upstream author's details.
 */
class LegalController extends Controller
{
    public function privacy(): View
    {
        return $this->page('privacy');
    }

    public function terms(): View
    {
        return $this->page('terms');
    }

    private function page(string $key): View
    {
        /** @var array{title: string, intro: string, sections: array<int, array{h: string, p: string}>} $content */
        $content = __("legal.{$key}");

        return view('legal.show', [
            'title' => $content['title'],
            'description' => $content['intro'],
            'content' => $content,
            'updated' => __('legal.updated'),
            'operator' => (string) config('nexo.legal.operator'),
            'contact' => (string) config('nexo.legal.contact'),
        ]);
    }
}
