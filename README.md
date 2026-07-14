# ArticleFeedback

Adds a "Give feedback" tab to article pages. Submissions are posted to a Discord webhook.

## Configuration

Reads the following environment variables directly (no `$wg` config yet):

- `ARTICLE_FEEDBACK_DISCORD_HOOK`: Discord webhook URL feedback is posted to. Required;

- `ARTICLE_FEEDBACK_DISCORD_GUILD_ID` / `ARTICLE_FEEDBACK_DISCORD_CHANNEL_ID`: optional, used to open a Discord widget (WidgetBot Crate) so the reader can see replies after submitting.

## License

MIT
