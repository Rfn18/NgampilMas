<?php

test('the example environment keeps debug mode disabled', function () {
    $example = file_get_contents(base_path('.env.example'));

    expect($example)->toBeString()->toMatch('/^APP_DEBUG=false$/m');
});
