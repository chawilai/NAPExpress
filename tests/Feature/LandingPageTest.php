<?php

use Inertia\Testing\AssertableInertia as Assert;

test('landing page is displayed', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
        );
});
