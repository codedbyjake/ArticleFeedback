<?php

namespace MediaWiki\Extension\ArticleFeedback;

use MediaWiki\Api\ApiBase;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Lets a reader (logged in or anonymous) leave feedback on an article.
 */
class ApiArticleFeedback extends ApiBase {

    private const MAX_LENGTH = 1000;

    public function execute(): void {
        $user = $this->getUser();

        if ( $user->pingLimiter( 'articlefeedback' ) ) {
            $this->dieWithError( 'apierror-ratelimited' );
        }

        $webhook = getenv( 'ARTICLE_FEEDBACK_DISCORD_HOOK' );
        if ( !$webhook ) {
            $this->dieWithError( [ 'rawmessage', 'Article feedback is not configured on this wiki.' ] );
        }

        $params = $this->extractRequestParams();
        $title = Title::newFromText( $params['title'] );
        if ( !$title || !$title->exists() ) {
            $this->dieWithError( 'apierror-missingtitle' );
        }

        $text = trim( $params['text'] );
        if ( $text === '' ) {
            $this->dieWithError( [ 'apierror-missingparam', 'text' ] );
        }
        if ( mb_strlen( $text ) > self::MAX_LENGTH ) {
            $text = mb_substr( $text, 0, self::MAX_LENGTH ) . '…';
        }

        if ( $user->isRegistered() ) {
            $name = $user->getName();
            $userPage = Title::makeTitle( NS_USER, $name )->getFullURL();
            $talkPage = Title::makeTitle( NS_USER_TALK, $name )->getFullURL();
            $contribs = SpecialPage::getTitleFor( 'Contributions', $name )->getFullURL();
            $who = "[{$name}]({$userPage}) ([t]({$talkPage})|[c]({$contribs}))";
        } else {
            $who = 'An anonymous reader';
        }

        $line = "💬 {$who} left feedback on [{$title->getPrefixedText()}]({$title->getFullURL()})";

        $quoted = '> ' . str_replace( "\n", "\n> ", $text );

        $payload = [
            'username'   => 'Article feedback',
            'avatar_url' => 'https://consumerrights.wiki/images/2/2b/Whlogo.webp',
            'content'    => "{$line}\n{$quoted}",
            'flags'      => 4,
        ];

        DiscordWebhook::send( $webhook, $payload );

        $this->getResult()->addValue( null, $this->getModuleName(), [ 'result' => 'success' ] );
    }

    public function getAllowedParams(): array {
        return [
            'title' => [
                ParamValidator::PARAM_TYPE => 'string',
                ParamValidator::PARAM_REQUIRED => true,
            ],
            'text' => [
                ParamValidator::PARAM_TYPE => 'string',
                ParamValidator::PARAM_REQUIRED => true,
            ],
        ];
    }

    public function needsToken(): string {
        return 'csrf';
    }

    public function isWriteMode(): bool {
        return true;
    }

    public function mustBePosted(): bool {
        return true;
    }
}
