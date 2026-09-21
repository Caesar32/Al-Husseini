<?php

test('the admin dashboard returns a successful response', function () {
    $response = $this->get('/admin');

    $response->assertStatus(200);
});
