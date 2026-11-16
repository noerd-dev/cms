<?php

declare(strict_types=1);

use Noerd\Cms\Support\SafeUrl;

uses(Noerd\Cms\Tests\TestCase::class);

it('allows web, mail and phone links, fragments and site paths', function (string $url): void {
    expect(SafeUrl::isSafe($url))->toBeTrue()
        ->and(SafeUrl::href($url))->toBe($url);
})->with([
    'https' => ['https://example.com/a?b=1'],
    'http' => ['http://example.com'],
    'mailto' => ['mailto:info@example.com'],
    'tel' => ['tel:+4989123'],
    'site path' => ['/kontakt'],
    'relative path' => ['kontakt'],
    'fragment' => ['#top'],
    'query' => ['?page=2'],
]);

it('refuses script schemes and other hosts in every spelling', function (string $url): void {
    expect(SafeUrl::isSafe($url))->toBeFalse()
        ->and(SafeUrl::href($url))->toBe('#')
        ->and(SafeUrl::orNull($url))->toBeNull()
        ->and(SafeUrl::cssUrl($url))->toBe('');
})->with([
    'javascript' => ['javascript:alert(1)'],
    'javascript upper case' => ['JavaScript:alert(1)'],
    'javascript with a tab' => ["java\tscript:alert(1)"],
    'javascript with leading control characters' => ["\x01 javascript:alert(1)"],
    'data' => ['data:text/html;base64,PHNjcmlwdD4='],
    'vbscript' => ['vbscript:msgbox(1)'],
    'protocol relative' => ['//evil.com'],
    'backslashes' => ['\\\\evil.com'],
    'slash backslash' => ['/\\evil.com'],
    'empty' => [''],
]);

it('encodes quotes and parentheses for a CSS url()', function (): void {
    expect(SafeUrl::cssUrl("/a.jpg') ; background: url('x"))->toBe('/a.jpg%27%29%20;%20background:%20url%28%27x');
});

it('knows a path of this site from another host', function (): void {
    expect(SafeUrl::isSitePath('/agentur'))->toBeTrue()
        ->and(SafeUrl::isSitePath('//evil.com'))->toBeFalse()
        ->and(SafeUrl::isSitePath('https://evil.com'))->toBeFalse()
        ->and(SafeUrl::isSitePath('agentur'))->toBeFalse()
        ->and(SafeUrl::isSitePath(null))->toBeFalse();
});
