<?php
$content = file_get_contents('public/assets/css/global.css');

$parts = [
    'base.css' => [1, 996],
    'ui.css' => [997, 1185],
    'dashboard.css' => [1186, 1514],
    'storefront.css' => [1515, 1672]
];

$lines = explode("\n", $content);
@mkdir('public/assets/css/modules', 0755, true);

$imports = [];
foreach ($parts as $name => $range) {
    $start = $range[0] - 1;
    $length = $range[1] - $range[0] + 1;
    $slice = array_slice($lines, $start, $length);
    file_put_contents('public/assets/css/modules/' . $name, implode("\n", $slice));
    $imports[] = "@import url('./modules/{$name}');";
}

file_put_contents('public/assets/css/global.css', implode("\n", $imports));
echo "Split global.css successfully.";
