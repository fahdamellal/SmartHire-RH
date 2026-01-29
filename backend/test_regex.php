<?php

$message = "Besoin d un commercial expérimenté";
$m = mb_strtolower($message);

$patterns = [
    '/(?:cherche|besoin\s+d.?\s*un?e?|recrute)\s+(?:un?e?)?\s*([a-zàâäéèêëïîôöùûüç\s\-]+?)(?:\s+à|\s+en|\s+pour|\s+avec|$)/u',
    '/profil\s+(?:de\s+)?([a-zàâäéèêëïîôöùûüç\s\-]+?)(?:\s+à|\s+en|\s+pour|$)/u',
];

foreach ($patterns as $i => $pattern) {
    echo "Pattern " . ($i + 1) . ":\n";
    if (preg_match($pattern, $m, $matches)) {
        echo "✅ MATCH!\n";
        echo "Capture: '" . $matches[1] . "'\n";
    } else {
        echo "❌ NO MATCH\n";
    }
    echo "\n";
}
