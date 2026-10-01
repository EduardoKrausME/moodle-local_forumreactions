// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * reactions.js
 *
 * @package   local_forumreactions
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(["jquery", "core/ajax", "core/templates", "core/notification"], function ($, Ajax, Templates, Notification) {
    const SELECTOR_POST = 'article[data-region="post"][data-post-id]';
    const SELECTOR_REACTIONS = '[data-region="forum-reactions"]';
    const SELECTOR_ACTIONS = '[data-region="post-actions-container"]';
    let config = {};
    let loading = false;
    let queued = false;

    const getPostIds = function () {
        const ids = [];
        $(SELECTOR_POST).each(function () {
            const postId = parseInt($(this).attr("data-post-id"), 10);
            if (postId && !$(this).find(SELECTOR_REACTIONS).length) {
                ids.push(postId);
            }
        });
        return [...new Set(ids)].slice(0, 200);
    };

    const getPostElement = function (postId) {
        return $(SELECTOR_POST).filter('[data-post-id="' + postId + '"]').first();
    };

    const insertRendered = function (postId, html, js) {
        const $post = getPostElement(postId);
        if (!$post.length || $post.find(SELECTOR_REACTIONS).length) {
            return;
        }

        const $content = $post.find(".content-alignment-container").first();
        if (!$content.length) {
            return;
        }

        const $actions = $content.find(SELECTOR_ACTIONS).first();
        const $actionsRow = $actions.closest(".d-flex.flex-wrap");
        if ($actionsRow.length) {
            $actionsRow.before(html);
        } else {
            $content.append(html);
        }
        Templates.runTemplateJS(js);
    };

    const renderPost = function (data, replace) {
        const templateData = {
            postid: data.postid,
            canreact: data.canreact,
            reactions: data.reactions.map(function (reaction) {
                return Object.assign({}, reaction, {canreact: data.canreact});
            }),
        };

        return Templates.renderForPromise("local_forumreactions/reactions", templateData)
            .then(function (result) {
                if (replace) {
                    const $existing = getPostElement(data.postid).find(SELECTOR_REACTIONS).first();
                    if ($existing.length) {
                        $existing.replaceWith(result.html);
                        Templates.runTemplateJS(result.js);
                        return;
                    }
                }
                insertRendered(data.postid, result.html, result.js);
            });
    };

    const load = function () {
        if (loading) {
            queued = true;
            return;
        }

        const postIds = getPostIds();
        if (!postIds.length) {
            return;
        }

        console.log(config);

        loading = true;
        const requests = Ajax.call([{
            methodname: "local_forumreactions_get_reactions",
            args: {
                cmid: config.cmid,
                postids: postIds,
            },
        }]);

        requests[0]
            .then(function (posts) {
                return Promise.all(posts.map(function (post) {
                    return renderPost(post, false);
                }));
            })
            .catch(Notification.exception)
    };

    const toggle = function ($button) {
        const $container = $button.closest(SELECTOR_REACTIONS);
        const postId = parseInt($container.attr("data-post-id"), 10);
        const reaction = $button.attr("data-reaction");

        if (!postId || !reaction || $button.prop("disabled")) {
            return;
        }

        $container.find("button").prop("disabled", true);
        const requests = Ajax.call([{
            methodname: "local_forumreactions_toggle_reaction",
            args: {
                cmid: config.cmid,
                postid: postId,
                reaction: reaction,
            },
        }]);

        requests[0]
            .then(function (post) {
                return renderPost(post, true);
            })
            .catch(function (error) {
                $container.find("button").prop("disabled", false);
                Notification.exception(error);
            });
    };

    const observe = function () {
        const observer = new MutationObserver(function (mutations) {
            let shouldLoad = false;
            mutations.forEach(function (mutation) {
                mutation.addedNodes.forEach(function (node) {
                    if (node.nodeType !== Node.ELEMENT_NODE) {
                        return;
                    }
                    if ($(node).is(SELECTOR_POST) || $(node).find(SELECTOR_POST).length) {
                        shouldLoad = true;
                    }
                });
            });
            if (shouldLoad) {
                window.setTimeout(load, 50);
            }
        });
        observer.observe(document.body, {childList: true, subtree: true});
    };

    const init = function (options) {
        config = options;
        console.log(config);
        $(document).on("click", '[data-action="toggle-reaction"]', function (event) {
            event.preventDefault();
            toggle($(this));
        });
        load();
        observe();
    };

    return {init: init};
});
