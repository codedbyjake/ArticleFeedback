<?php

namespace MediaWiki\Extension\ArticleFeedback;

use MediaWiki\Api\ApiBase;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\WikitextContent;
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
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

        $talkTitle = $title->getTalkPage();
        $this->checkTitleUserPermissions( $talkTitle, 'edit' );

        if ( $user->isRegistered() ) {
            $name = $user->getName();
            $userPage = Title::makeTitle( NS_USER, $name )->getFullURL();
            $talkPage = Title::makeTitle( NS_USER_TALK, $name )->getFullURL();
            $contribs = SpecialPage::getTitleFor( 'Contributions', $name )->getFullURL();
            $whoMarkdown = "[{$name}]({$userPage}) ([t]({$talkPage})|[c]({$contribs}))";
            $whoWikitext = "[[User:{$name}|{$name}]]";
        } else {
            $whoMarkdown = 'An anonymous reader';
            $whoWikitext = 'an anonymous reader';
        }

        $this->postToTalkPage( $talkTitle, $whoWikitext, $text );

        $webhook = getenv( 'ARTICLE_FEEDBACK_DISCORD_HOOK' );
        if ( $webhook ) {
            $line = "💬 {$whoMarkdown} left feedback on [{$title->getPrefixedText()}]({$title->getFullURL()})";
            $quoted = '> ' . str_replace( "\n", "\n> ", $text );

            $payload = [
                'username'   => 'Article feedback',
                'avatar_url' => 'https://consumerrights.wiki/images/2/2b/Whlogo.webp',
                'content'    => "{$line}\n{$quoted}",
                'flags'      => 4,
            ];

            DiscordWebhook::send( $webhook, $payload );
        }

        $this->getResult()->addValue( null, $this->getModuleName(), [ 'result' => 'success' ] );
    }

    private function postToTalkPage( Title $talkTitle, string $whoWikitext, string $text ): void {
        $botUser = $this->getBotUser();

        $wikiPage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $talkTitle );
        $oldContent = $wikiPage->getContent( RevisionRecord::RAW ) ?? new WikitextContent( '' );

        $heading = $this->msg( 'articlefeedback-talk-heading', $whoWikitext )->inContentLanguage()->plain();
        $rawBody = $this->msg( 'articlefeedback-talk-body', wfEscapeWikiText( $text ) )->inContentLanguage()->plain();

        // Expand the ~~~~ in the message into a real signature, the same way MediaWiki
        // does for a normal edit. DiscussionTools only recognises a section as a
        // threaded comment (reply button, comment count, etc.) if it ends in one.
        $parser = MediaWikiServices::getInstance()->getParserFactory()->getInstance();
        $parserOptions = ParserOptions::newFromUser( $botUser );
        $body = $parser->preSaveTransform( $rawBody, $talkTitle, $botUser, $parserOptions );

        $newContent = $oldContent->replaceSection( 'new', new WikitextContent( $body ), $heading );

        $updater = $wikiPage->newPageUpdater( $botUser );
        $updater->setContent( SlotRecord::MAIN, $newContent );
        $updater->saveRevision(
            CommentStoreComment::newUnsavedComment( $this->msg( 'articlefeedback-talk-summary' )->inContentLanguage()->text() )
        );
    }

    private function getBotUser(): User {
        $botUser = User::newFromName( $this->msg( 'articlefeedback-botname' )->inContentLanguage()->text() );

        if ( !$botUser->isRegistered() ) {
            $botUser->addToDatabase();
            MediaWikiServices::getInstance()->getUserGroupManager()->addUserToGroup( $botUser, 'bot' );
        }

        return $botUser;
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
