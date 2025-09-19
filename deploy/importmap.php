<?php

/**
 * Returns the importmap for this application.
 *
 * - "path" is a path inside the asset mapper system. Use the
 *     "debug:asset-map" command to see the full list of paths.
 *
 * - "entrypoint" (JavaScript only) set to true for any module that will
 *     be used as an "entrypoint" (and passed to the importmap() Twig function).
 *
 * The "importmap:require" command can be used to add new entries to this file.
 */
return [
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
    '@hotwired/stimulus' => [
        'version' => '3.2.2',
    ],
    '@symfony/stimulus-bundle' => [
        'path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js',
    ],
    '@hotwired/turbo' => [
        'version' => '7.3.0',
    ],
    'lodash-es' => [
        'version' => '4.17.21',
    ],
    'blurhash' => [
        'version' => '2.0.5',
    ],
    'marked' => [
        'version' => '4.0.12',
    ],
    'turndown' => [
        'version' => '7.2.0',
    ],
    'turndown-plugin-gfm' => [
        'version' => '1.0.2',
    ],
    'color-parse' => [
        'version' => '1.4.2',
    ],
    'color-convert' => [
        'version' => '2.0.1',
    ],
    'vanilla-colorful/lib/entrypoints/hex' => [
        'version' => '0.7.2',
    ],
    'color-name' => [
        'version' => '1.1.4',
    ],
];
