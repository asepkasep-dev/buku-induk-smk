<?php

test('the application root sends guests to the login screen', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('login'));
});
