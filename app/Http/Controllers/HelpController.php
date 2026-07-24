<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

// Help center. FAQ items are translatable: lang/<locale>/help.php defines the
// list as `faqs => [['q' => ..., 'a' => ...], ...]`. The contact target is
// instance-configurable (support form/email), falling back to the attribution
// URL (the project repo) so a fresh self-host still has a working "contact us".
class HelpController extends Controller
{
    public function __invoke(): View
    {
        $contactUrl = config('nexo.support_url')
            ?: (config('nexo.support_email')
                ? 'mailto:'.config('nexo.support_email')
                : config('nexo.attribution.url'));

        return view('help.index', ['contactUrl' => $contactUrl]);
    }
}
