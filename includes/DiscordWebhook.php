<?php

namespace MediaWiki\Extension\ArticleFeedback;

class DiscordWebhook {

    public static function send( string $url, array $payload ): void {
        $json = json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

        $ch = curl_init( $url );
        curl_setopt_array( $ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [ 'Content-Type: application/json' ],
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 10,
        ] );
        curl_exec( $ch );
        curl_close( $ch );
    }

}
