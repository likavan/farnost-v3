<?php

declare(strict_types=1);

// Načítaj Resolver bez WordPressu — používa pure-function logiku.
if (!defined('FARNOST_RESOLVER_STANDALONE')) {
    define('FARNOST_RESOLVER_STANDALONE', true);
}
require_once __DIR__ . '/../../web/app/plugins/farnost-plugin/src/Schedule/Resolver.php';

use Farnost\Plugin\Schedule\Resolver;

test('rozpis: vracia len omše daného dňa v týždni', function () {
    $rozpis = [
        ['day_of_week' => 'mon', 'time' => '18:00'],
        ['day_of_week' => 'sun', 'time' => '08:00'],
        ['day_of_week' => 'sun', 'time' => '10:30'],
    ];
    // 2026-05-17 je nedeľa
    $result = Resolver::resolve($rozpis, [], '2026-05-17');
    expect($result)->toHaveCount(2);
    expect($result[0]['cas'])->toBe('08:00');
    expect($result[1]['cas'])->toBe('10:30');
});

test('rozpis: deň bez omše vracia prázdne pole', function () {
    $rozpis = [
        ['day_of_week' => 'sun', 'time' => '08:00'],
    ];
    // 2026-05-18 je pondelok
    $result = Resolver::resolve($rozpis, [], '2026-05-18');
    expect($result)->toBe([]);
});

test('výnimka sa pridáva aditívne k pravidelnému rozpisu', function () {
    $rozpis = [
        ['day_of_week' => 'mon', 'time' => '18:00'],
    ];
    $vynimky = [
        ['cas' => '14:00', 'oznacenie' => 'pohrebná', 'umysel' => 'za zosnulú p. N.'],
    ];
    // 2026-05-18 (pondelok) — výnimka pridáva mimoriadnu omšu k pravidelnej.
    $result = Resolver::resolve($rozpis, $vynimky, '2026-05-18');
    expect($result)->toHaveCount(2);
    expect($result[0]['cas'])->toBe('14:00');
    expect($result[0]['zdroj'])->toBe('vynimka');
    expect($result[0]['umysel'])->toBe('za zosnulú p. N.');
    expect($result[1]['cas'])->toBe('18:00');
    expect($result[1]['zdroj'])->toBe('rozpis');
});

test('zoradenie podľa času, bez ohľadu na zdroj', function () {
    $rozpis = [
        ['day_of_week' => 'sun', 'time' => '10:30'],
        ['day_of_week' => 'sun', 'time' => '08:00'],
    ];
    $vynimky = [
        ['cas' => '09:00', 'oznacenie' => 'mimoriadna', 'umysel' => ''],
    ];
    $result = Resolver::resolve($rozpis, $vynimky, '2026-05-17');
    expect($result)->toHaveCount(3);
    expect(array_column($result, 'cas'))->toBe(['08:00', '09:00', '10:30']);
});

test('označenie sa zachová z rozpisu', function () {
    $rozpis = [
        ['day_of_week' => 'wed', 'time' => '18:00', 'oznacenie' => 'detská'],
    ];
    // 2026-05-20 je streda
    $result = Resolver::resolve($rozpis, [], '2026-05-20');
    expect($result[0]['oznacenie'])->toBe('detská');
});

test('rozpis bez výnimiek pre konkrétny dátum', function () {
    $rozpis = [
        ['day_of_week' => 'sat', 'time' => '07:30'],
    ];
    // 2026-05-23 je sobota
    $result = Resolver::resolve($rozpis, [], '2026-05-23');
    expect($result)->toHaveCount(1);
    expect($result[0])->toMatchArray([
        'cas'       => '07:30',
        'oznacenie' => '',
        'umysel'    => '',
        'zdroj'     => 'rozpis',
    ]);
});

test('iba výnimky bez pravidelného rozpisu', function () {
    $vynimky = [
        ['cas' => '15:00', 'oznacenie' => 'pohrebná', 'umysel' => 'za zosnulých'],
        ['cas' => '17:00', 'oznacenie' => '', 'umysel' => 'za farníkov'],
    ];
    $result = Resolver::resolve([], $vynimky, '2026-05-18');
    expect($result)->toHaveCount(2);
    expect($result[0]['cas'])->toBe('15:00');
    expect($result[1]['cas'])->toBe('17:00');
});

test('dayKeyForDate: ISO day mapping', function () {
    expect(Resolver::dayKeyForDate('2026-05-18'))->toBe('mon');
    expect(Resolver::dayKeyForDate('2026-05-19'))->toBe('tue');
    expect(Resolver::dayKeyForDate('2026-05-20'))->toBe('wed');
    expect(Resolver::dayKeyForDate('2026-05-21'))->toBe('thu');
    expect(Resolver::dayKeyForDate('2026-05-22'))->toBe('fri');
    expect(Resolver::dayKeyForDate('2026-05-23'))->toBe('sat');
    expect(Resolver::dayKeyForDate('2026-05-24'))->toBe('sun');
});

test('dayKeyForDate: nesprávny vstup vracia prázdny reťazec', function () {
    expect(Resolver::dayKeyForDate('not-a-date'))->toBe('');
});
