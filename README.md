# ArticleFeedback

A MediaWiki extension that adds a "Give feedback" tab to article pages. Submissions are posted as a new section on the article's talk page, and optionally to a Discord webhook.

## How it works

The talk-page post is made by a dedicated bot account (auto-created on first use), quoting who actually left the feedback in the section body. Logged-in submitters are linked to their user page; anonymous submitters are credited as "an anonymous reader".

## Configuration

Reads the following environment variables directly:

- `ARTICLE_FEEDBACK_DISCORD_HOOK`: Discord webhook URL feedback is also posted to. Optional; if unset, feedback still posts to the talk page, it just skips Discord.
- `ARTICLE_FEEDBACK_DISCORD_GUILD_ID` / `ARTICLE_FEEDBACK_DISCORD_CHANNEL_ID`: optional, used to open a Discord widget (WidgetBot Crate) so the reader can see replies after submitting.

## Structure

```
ArticleFeedback/
├── extension.json
├── includes/
│   ├── ApiArticleFeedback.php
│   ├── DiscordWebhook.php
│   └── Hooks.php
├── i18n/
│   ├── en.json
│   └── qqq.json
├── LICENSE
└── README.md
```

## License

MIT
