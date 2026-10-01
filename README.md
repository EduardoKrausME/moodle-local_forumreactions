# Forum reactions (`local_forumreactions`)

Forum Reactions adds lightweight emoji reactions to posts in Moodle's standard Forum activity without modifying
`mod_forum`.

## How it works

Reaction controls are added to visible forum posts and update through AJAX without reloading the page. Posts inserted
dynamically after the initial page load are detected as well, so reactions continue to work while discussions expand.

The plugin follows the Forum visibility rules before returning or changing reaction data.

## Reactions

The default set includes:

- 👍 Like;
- ❤️ Love;
- 😂 Funny;
- 🎉 Celebrate;
- 🤔 Interesting;
- 😕 I did not understand.

Administrators can choose which reactions are available, allow more than one reaction per user and post, and decide
whether users may react to their own posts.

## Data behaviour

Reaction counts are loaded in a batched request for the posts currently displayed. Stored reactions are removed when the
corresponding post or Forum activity is deleted, and the plugin exposes its stored user data through Moodle's Privacy
API.

Settings are available in **Site administration → Plugins → Local plugins → Forum reactions**.
