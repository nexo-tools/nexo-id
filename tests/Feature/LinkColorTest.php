<?php

// Text links use the link token, never the button fill. `text-primary` is the
// colour of a primary button's background: on the dark card it measured 3.45:1
// («¿Olvidaste tu contraseña?», «Volver al inicio», «Seguir con la sesión»),
// under WCAG's 4.5:1 for text. `--nexo-link` (violet-700 light, violet-300
// dark) is the brand's text-link colour, the one the shared chrome and the
// other Nexo apps already use (`text-link`).

it('no link or summary in the views is coloured with the button fill', function () {
    $offenders = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views'), FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if (! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }

        $source = (string) file_get_contents($file->getPathname());
        preg_match_all('/<(?:a|summary)\b[^>]*\bclass="([^"]*)"/s', $source, $matches);

        foreach ($matches[1] as $class) {
            if (preg_match('/(?:^|\s)(?:hover:)?text-primary(?:-hover)?(?:\s|$)/', $class) === 1) {
                $offenders[] = str_replace(resource_path('views').'/', '', $file->getPathname()).': '.$class;
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('the link token exists in the Tailwind theme', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($css)->toContain('--color-link: var(--nexo-link);')
        ->and($css)->toContain('--color-link-hover: var(--nexo-link-hover);');
});
