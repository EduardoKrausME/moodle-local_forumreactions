# Forum reactions (`local_forumreactions`)

Adds lightweight emoji reactions to posts in Moodle's standard Forum activity without modifying `mod_forum`.

## Features

- 👍 Like, ❤️ Love, 😂 Funny, 🎉 Celebrate, 🤔 Interesting and 😕 I did not understand.
- AJAX updates without page reload.
- One batched read request for all forum posts currently displayed.
- Supports dynamically inserted forum posts/replies through a `MutationObserver`.
- Uses the standard Forum visibility rules before returning or changing reaction data.
- Configurable enabled reactions.
- Optional multiple reactions per user/post.
- Optional reactions to own posts.
- Privacy API implementation.
- Removes stored reactions when a post or entire Forum activity is deleted.

## Requirements

- Moodle 4.5 or later.

## Installation

Install the folder as:

`local/forumreactions`

Then complete the Moodle upgrade process.

Settings are available in:

Site administration → Plugins → Local plugins → Forum reactions
