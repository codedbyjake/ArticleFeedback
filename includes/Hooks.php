<?php

namespace MediaWiki\Extension\ArticleFeedback;

use IContextSource;
use MediaWiki\Html\Html;
use MediaWiki\Output\OutputPage;
use MediaWiki\Skin\Skin;
use MediaWiki\Skin\SkinTemplate;
use MediaWiki\Title\Title;

class Hooks {

    private static function isFeedbackEligible( ?Title $title, IContextSource $context ): bool {
        return $title
            && $title->inNamespace( NS_MAIN )
            && $title->exists()
            && $context->getActionName() === 'view'
            && !$context->getRequest()->getCheck( 'diff' );
    }

    /**
     * Adds a "Give feedback" tab on article pages.
     */
    public static function onSkinTemplateNavigation__Universal( SkinTemplate $skin, array &$links ): void {
        if ( !self::isFeedbackEligible( $skin->getTitle(), $skin->getContext() ) ) {
            return;
        }

        $links['views']['give-feedback'] = [
            'id' => 'ca-give-feedback',
            'text' => 'Give feedback',
            'href' => '#',
            'link-html' => '<svg class="crw-feedback-icon" width="14" height="14" viewBox="0 0 20 20" ' .
                'xmlns="http://www.w3.org/2000/svg" fill="currentColor" aria-hidden="true">' .
                '<rect x="2" y="2" width="16" height="11" rx="2"/><polygon points="6,13 6,17 10,13"/></svg>',
        ];
    }

    public static function onBeforePageDisplay( OutputPage $out, Skin $skin ): void {
        $title = $out->getTitle();
        if ( !self::isFeedbackEligible( $title, $skin->getContext() ) ) {
            return;
        }

        $out->addModules( [ 'mediawiki.api' ] );
        $out->addInlineStyle( <<<CSS
.crw-feedback-icon {
    display: inline-block;
    margin-right: 0.3em;
    vertical-align: middle;
    position: relative;
    top: 3px;
}
CSS
        );

        $pageName = Html::encodeJsVar( $title->getPrefixedText() );
        $discordGuildId = Html::encodeJsVar( (string)getenv( 'ARTICLE_FEEDBACK_DISCORD_GUILD_ID' ) ?: null );
        $discordChannelId = Html::encodeJsVar( (string)getenv( 'ARTICLE_FEEDBACK_DISCORD_CHANNEL_ID' ) ?: null );

        $out->addInlineScript( <<<JS
(window.RLQ = window.RLQ || []).push( function () {
    var discordGuildId = {$discordGuildId};
    var discordChannelId = {$discordChannelId};
    var cratePromise = null;

    function openFeedbackChannel() {
        if ( !discordGuildId || !discordChannelId ) {
            return;
        }
        if ( !cratePromise ) {
            cratePromise = new Promise( function ( resolve, reject ) {
                var script = document.createElement( 'script' );
                script.src = 'https://cdn.jsdelivr.net/npm/@widgetbot/crate@3';
                script.onload = function () {
                    resolve( new window.Crate( {
                        server: discordGuildId,
                        channel: discordChannelId
                    } ) );
                };
                script.onerror = reject;
                document.body.appendChild( script );
            } );
        }
        cratePromise.then( function ( crate ) {
            crate.toggle( true );
        } ).catch( function () {
        } );
    }

    var tabLink = document.querySelector( '#ca-give-feedback a' );
    if ( !tabLink ) {
        return;
    }
    tabLink.addEventListener( 'click', function ( event ) {
        event.preventDefault();
        mw.loader.using( 'oojs-ui-windows' ).done( function () {
            var message = new OO.ui.HtmlSnippet(
                '<p>What feedback would you like to share about this article?</p>' +
                '<p style="font-size:0.85em;color:#54595d;margin-top:0.5em;">' +
                'Feedback is posted to our Discord community channel (also crossposted to our Zulip community), where our editors can see it and reply.' +
                '</p>'
            );
            OO.ui.prompt( message, {
                textInput: { multiline: true, rows: 4 }
            } ).done( function ( text ) {
                if ( !text ) {
                    return;
                }
                new mw.Api().postWithToken( 'csrf', {
                    action: 'articlefeedback',
                    title: {$pageName},
                    text: text
                } ).done( function () {
                    mw.notify( 'Thanks - your feedback has been sent!' );
                    openFeedbackChannel();
                } ).fail( function () {
                    mw.notify( 'Sorry, that didn\\'t go through. Please try again later.', { type: 'error' } );
                } );
            } );
        } );
    } );
} );
JS
        );
    }

}
