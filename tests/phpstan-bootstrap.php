<?php

// Larastan's view-string inference calls Laravel's view() helper, which Flarum
// intentionally does not ship. Supply it only inside the analysis process.
if (! function_exists('view')) {
    function view(?string $view = null, array $data = [], array $mergeData = []): \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
    {
        $factory = resolve(\Illuminate\Contracts\View\Factory::class);

        return $view === null ? $factory : $factory->make($view, $data, $mergeData);
    }
}
