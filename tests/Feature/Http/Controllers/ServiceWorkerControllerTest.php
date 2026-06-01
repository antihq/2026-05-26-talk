<?php

test('guest can fetch service worker', function () {
    $response = $this->get('/sw.js');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/javascript');
    $response->assertHeader('Cache-Control');
    expect($response->headers->get('Cache-Control'))->toContain('no-cache');
});

test('service worker response contains push event listener', function () {
    $response = $this->get('/sw.js');

    $response->assertSee("addEventListener('push'", false);
});

test('service worker response contains notification click listener', function () {
    $response = $this->get('/sw.js');

    $response->assertSee("addEventListener('notificationclick'", false);
});
