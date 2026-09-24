<?php

test('living office route is available', function () {
    $this->get('/living-office')
        ->assertOk()
        ->assertSee('living-office-root');
});
