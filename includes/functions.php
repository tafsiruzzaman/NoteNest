<?php
function sanitize_input($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}
function is_image($mime) {
    return strpos($mime, 'image/') === 0;
}
function is_text($mime) {
    return strpos($mime, 'text/') === 0;
}

function is_pdf($mime) {
    return strpos($mime, 'application/pdf') === 0;
}
?>
