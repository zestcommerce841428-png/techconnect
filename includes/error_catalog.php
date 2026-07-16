<?php
/**
 * Central metadata for every HTTP error page the site serves. Deliberately
 * has zero dependencies (no db.php/auth.php) since a 5xx — or a 4xx thrown
 * while the DB itself is unreachable — must still be able to render this.
 */
function error_catalog(): array
{
    return [
        // 4xx — client errors
        400 => ['Bad request', 'The request could not be understood due to malformed syntax.', '⚠️'],
        401 => ['Authentication required', 'You need to sign in to view this page.', '🔒'],
        402 => ['Payment required', 'This content requires an active payment or subscription.', '💳'],
        403 => ['Access denied', "You don't have permission to view this page.", '🚫'],
        405 => ['Method not allowed', 'That request method is not supported for this page.', '🛑'],
        406 => ['Not acceptable', "The server can't produce a response matching what your browser requested.", '⚠️'],
        407 => ['Proxy authentication required', 'A proxy server rejected the request until you authenticate with it.', '🔒'],
        408 => ['Request timeout', 'Your request took too long to complete. Please try again.', '⏱️'],
        409 => ['Conflict', 'This request conflicts with the current state of the resource.', '⚡'],
        410 => ['Gone', 'This page used to exist but has been permanently removed.', '🗑️'],
        411 => ['Length required', 'The request must specify a Content-Length header.', '⚠️'],
        412 => ['Precondition failed', "A condition your request relied on wasn't met.", '⚠️'],
        413 => ['Payload too large', "The file or data you sent is too large. Try a smaller one.", '📦'],
        414 => ['URI too long', 'That web address is too long for us to process.', '🔗'],
        415 => ['Unsupported media type', "That file type isn't supported here.", '📄'],
        416 => ['Range not satisfiable', "The requested content range can't be fulfilled.", '⚠️'],
        417 => ['Expectation failed', "The server can't meet the requirements of your Expect header.", '⚠️'],
        418 => ["I'm a teapot", "The server refuses to brew coffee because it is, permanently, a teapot.", '🫖'],
        419 => ['Session expired', 'Your form session has expired. Please refresh the page and try again.', '⏳'],
        420 => ['Slow down', "You're doing that a bit too fast. Please wait a moment and retry.", '🐢'],
        421 => ['Misdirected request', "This request was sent to a server that can't produce a response.", '⚠️'],
        422 => ['Unprocessable content', 'The data submitted was well-formed but failed validation.', '📝'],
        423 => ['Locked', 'This resource is currently locked and cannot be modified.', '🔒'],
        424 => ['Failed dependency', 'This request failed because a prior related request failed.', '⚡'],
        425 => ['Too early', 'The server is unwilling to process a request that might be replayed.', '⏱️'],
        426 => ['Upgrade required', 'Your client must switch to a different protocol to continue.', '⬆️'],
        428 => ['Precondition required', 'This request must include valid conditional headers.', '⚠️'],
        429 => ['Too many requests', "You've made too many requests recently. Please slow down and try again shortly.", '🚦'],
        431 => ['Header fields too large', 'Your request headers were too large for the server to process.', '⚠️'],
        440 => ['Login timeout', 'Your session has timed out. Please sign in again.', '⏳'],
        444 => ['No response', 'The connection was closed without a response.', '🔌'],
        451 => ['Unavailable for legal reasons', 'This content is unavailable for legal reasons.', '⚖️'],
        494 => ['Request header too large', 'Your request headers exceeded the size the server allows.', '⚠️'],
        499 => ['Client closed request', 'The connection was closed before the server could respond.', '🔌'],

        // 5xx — server errors
        500 => ['Something went wrong', "We hit an unexpected error on our end. Please try again in a moment.", '💥'],
        501 => ['Not implemented', "This feature isn't available yet.", '🚧'],
        502 => ['Bad gateway', 'We received an invalid response from an upstream server.', '🌐'],
        503 => ['Service unavailable', "We're temporarily down for maintenance. Please check back shortly.", '🛠️'],
        504 => ['Gateway timeout', 'An upstream server took too long to respond.', '⏱️'],
        505 => ['HTTP version not supported', "The server doesn't support the HTTP protocol version used in the request.", '⚠️'],
        506 => ['Variant also negotiates', 'A server misconfiguration caused a content-negotiation loop.', '⚠️'],
        507 => ['Insufficient storage', 'The server is out of storage space to complete this request.', '💾'],
        508 => ['Loop detected', 'The server detected an infinite loop while processing this request.', '🔁'],
        510 => ['Not extended', 'Further extensions to the request are required for the server to fulfill it.', '⚠️'],
        511 => ['Network authentication required', 'You need to authenticate with the network before continuing.', '🔒'],
        520 => ['Unknown error', 'An unexpected error occurred and the connection was reset.', '❓'],
        521 => ['Server is down', 'Our web server is currently down or refusing connections.', '🔌'],
        522 => ['Connection timed out', 'The connection to our server timed out.', '⏱️'],
        523 => ['Origin is unreachable', 'Our origin server could not be reached.', '🌐'],
        524 => ['A timeout occurred', 'The server started responding but the connection timed out.', '⏱️'],
        525 => ['SSL handshake failed', 'A secure connection to the server could not be established.', '🔒'],
        526 => ['Invalid SSL certificate', "The server's SSL certificate could not be validated.", '🔒'],
        527 => ['Railgun error', 'A connection between our infrastructure components failed.', '🌐'],
        529 => ['Site overloaded', "We're experiencing unusually high traffic. Please try again shortly.", '📈'],
        530 => ['Site frozen', 'This site has been temporarily frozen. Please try again later.', '❄️'],
    ];
}

function error_meta(int $code): array
{
    $catalog = error_catalog();
    if (isset($catalog[$code])) {
        [$title, $message, $icon] = $catalog[$code];
        return ['title' => $title, 'message' => $message, 'icon' => $icon];
    }
    if ($code >= 500) {
        return ['title' => 'Server error', 'message' => 'Something went wrong on our end. Please try again shortly.', 'icon' => '💥'];
    }
    return ['title' => 'Request error', 'message' => "We couldn't process that request.", 'icon' => '⚠️'];
}
