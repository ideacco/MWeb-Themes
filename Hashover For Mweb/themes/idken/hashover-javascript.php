// Copyright (C) 2010-2017 Jacob Barkdull
// This file is part of HashOver.
//
// HashOver is free software: you can redistribute it and/or modify
// it under the terms of the GNU Affero General Public License as
// published by the Free Software Foundation, either version 3 of the
// License, or (at your option) any later version.
//
// HashOver is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU Affero General Public License for more details.
//
// You should have received a copy of the GNU Affero General Public License
// along with HashOver.  If not, see <http://www.gnu.org/licenses/>.


// Initial HashOver object
var HashOver = {};

// FIXME: This will be split into multiple functions in separate files
HashOver.init = function ()
{
	"use strict";

	var execStart		= Date.now ();
	var httpRoot		= '/hashover';
	var URLRegex		= '((http|https|ftp):\/\/[a-z0-9-@:;%_\+.~#?&\/=]+)';
	var URLParts		= window.location.href.split ('#');
	var elementsById	= {};
	var trimRegex		= /^[\r\n]+|[\r\n]+$/g;
	var streamMode		= false;
	var streamDepth		= 3;
	var blockCodeRegex	= /```([\s\S]+?)```/g;
	var inlineCodeRegex	= /(^|[^a-z0-9`])`([^`]+?[\s\S]+?)`([^a-z0-9`]|$)/ig;
	var blockCodeMarker	= /CODE_BLOCK\[([0-9]+)\]/g;
	var inlineCodeMarker	= /CODE_INLINE\[([0-9]+)\]/g;
	var collapsedCount	= 0;
	var collapseLimit	= 3;
	var defaultName		= 'Anonymous';
	var allowsDislikes	= false;
	var allowsLikes		= false;
	var linkRegex		= new RegExp (URLRegex + '( {0,1})', 'ig');
	var imageRegex		= new RegExp ('\\[img\\]<a.*?>' + URLRegex + '</a>\\[/img\\]', 'ig');
	var imageExtensions	= ['jpeg', 'jpg', 'png', 'gif'];
	var imagePlaceholder	= '/hashover/images/place-holder.svg';
	var codeOpenRegex	= /<code>/i;
	var codeTagRegex	= /(<code>)([\s\S]*?)(<\/code>)/ig;
	var preOpenRegex	= /<pre>/i;
	var preTagRegex		= /(<pre>)([\s\S]*?)(<\/pre>)/ig;
	var lineRegex		= /(?:\r\n|\r|\n)/g;
	var paragraphRegex	= /(?:\r\n|\r|\n){2}/g;
	var serverEOL		= '\n';
	var doubleEOL		= serverEOL + serverEOL;
	var codeTagMarkerRegex	= /CODE_TAG\[([0-9]+)\]/g;
	var preTagMarkerRegex	= /PRE_TAG\[([0-9]+)\]/g;
	var messageCounts	= {};
	var userIsLoggedIn	= false;
	var primaryCount	= 23;
	var totalCount		= 31;
	var AJAXPost		= null;
	var AJAXEdit		= null;
	var httpScripts		= '/hashover/scripts';
	var commentStatuses	= ['approved', 'pending', 'deleted'];
	var moreLink		= null;
	var sortDiv		= null;
	var moreDiv		= null;
	var showingMore		= false;
	var pageURL		= 'http://tildehash.com/comments.html';
	var threadRegex		= /^(c[0-9r]+)r[0-9\-pop]+$/;
	var appendCSS		= true;
	var themeCSS		= httpRoot + '/themes/1.0-ported-yufan/style.css';
	var head		= document.head || document.getElementsByTagName ('head')[0];
	var URLHref		= URLParts[0];
	var HashOverDiv		= document.getElementById ('hashover');
	var hashoverScript	= 1;
	var deviceType		= 'desktop';
	var HashOverForm	= null;
	var collapseComments	= false;
	var URLHash		= URLParts[1] || '';

	// Array for inline code and code block markers
	var codeMarkers = {
		block: { marks: [], count: 0 },
		inline: { marks: [], count: 0 }
	};

	// Some locales, stored in JavaScript to avoid using a lot of PHP tags
	var locale = {
		cancel:			'取消',
		externalImageTip:	'点击查看外部图像',
		like:			['个赞', '个赞'],
		liked:			'已点赞',
		unlike:			'不赞了',
		likeComment:		'\'点赞\' 此评论',
		likedComment:		'取消此评论的赞',
		dislike:		['不喜欢', '不喜欢'],
		disliked:		'不喜欢',
		dislikeComment:		'\'不喜欢\' 这条评论',
		dislikedComment:	'你已经 \'不喜欢\' 这条评论了',
		name:			'昵称',
		password:		'密码',
		email:			'邮箱',
		website:		'网址'
	};

	// Markdown patterns to search for
	var markdownSearch = [
		/\*\*([^ *])([\s\S]+?)([^ *])\*\*/g,
		/\*([^ *])([\s\S]+?)([^ *])\*/g,
		/(^|\W)_([^_]+?[\s\S]+?)_(\W|$)/g,
		/__([^ _])([\s\S]+?)([^ _])__/g,
		/~~([^ ~])([\s\S]+?)([^ ~])~~/g
	];

	// HTML replacements for markdown patterns
	var markdownReplace = [
		'<strong>$1$2$3</strong>',
		'<em>$1$2$3</em>',
		'$1<u>$2</u>$3',
		'<u>$1$2$3</u>',
		'<s>$1$2$3</s>'
	];

	// Tags that will have their innerHTML trimmed
	var trimTagRegexes = {
		blockquote: {
			test: /<blockquote>/,
			replace: /(<blockquote>)([\s\S]*?)(<\/blockquote>)/ig
		},

		ul: {
			test: /<ul>/,
			replace: /(<ul>)([\s\S]*?)(<\/ul>)/ig
		},

		ol: {
			test: /<ol>/,
			replace: /(<ol>)([\s\S]*?)(<\/ol>)/ig
		}
	};

	// Field options
	var fieldOptions = {
		'name': true,
		'password': false,
		'email': true,
		'website': true
	};

	// Shorthand for Document.getElementById ()
	function getElement (id, force)
	{
		if (force === true) {
			return document.getElementById (id);
		}

		if (!elementsById[id]) {
			elementsById[id] = document.getElementById (id);
		}

		return elementsById[id];
	}

	// Execute callback function if element isn't false
	function ifElement (element, callback)
	{
		if (element = getElement (element, true)) {
			return callback (element);
		}

		return false;
	}

	// Trims leading and trailing newlines from a string
	function EOLTrim (string)
	{
		return string.replace (trimRegex, '');
	}

	// Trims whitespace from an HTML tag's inner HTML
	function tagTrimmer (fullTag, openTag, innerHTML, closeTag)
	{
		return openTag + EOLTrim (innerHTML) + closeTag;
	}

	// Find a comment by its permalink
	function findByPermalink (permalink, comments)
	{
		var comment;

		// Loop through all comments
		for (var i = 0, il = comments.length; i < il; i++) {
			// Return comment if its permalink matches
			if (comments[i].permalink === permalink) {
				return comments[i];
			}

			// Recursively check replies when present
			if (comments[i].replies !== undefined) {
				comment = findByPermalink (permalink, comments[i].replies);

				if (comment !== null) {
					return comment;
				}
			}
		}

		// Otherwise return null
		return null;
	}

	// Returns the permalink of a comment's parent
	function getParentPermalink (permalink, flatten)
	{
		flatten = flatten || false;

		var parent = permalink.split ('r');
		var length = parent.length - 1;

		// Limit depth if in stream mode
		if (streamMode === true && flatten === true) {
			length = Math.min (streamDepth, length);
		}

		// Check if there is a parent after flatten
		if (length > 0) {
			// If so, remove child from permalink
			parent = parent.slice (0, length);

			// Return parent permalink as string
			return parent.join ('r');
		}

		return null;
	}

	// Replaces markdown for inline code with a marker
	function codeReplace (fullTag, first, second, third, display)
	{
		var markName = 'CODE_' + display.toUpperCase ();
		var markCount = codeMarkers[display].count++;
		var codeMarker;

		if (display !== 'block') {
			codeMarker = first + markName + '[' + markCount + ']' + third;
			codeMarkers[display].marks[markCount] = EOLTrim (second);
		} else {
			codeMarker = markName + '[' + markCount + ']';
			codeMarkers[display].marks[markCount] = EOLTrim (first);
		}

		return codeMarker;
	}

	// Parses a string as markdown
	function parseMarkdown (string)
	{
		// Reset marker arrays
		codeMarkers = {
			block: { marks: [], count: 0 },
			inline: { marks: [], count: 0 }
		};

		// Replace code blocks with markers
		string = string.replace (blockCodeRegex, function (fullTag, first, second, third) {
			return codeReplace (fullTag, first, second, third, 'block');
		});

		// Break string into paragraphs
		var paragraphs = string.split (paragraphRegex);

		// Run through each paragraph replacing markdown patterns
		for (var i = 0, il = paragraphs.length; i < il; i++) {
			// Replace code tags with marker text
			paragraphs[i] = paragraphs[i].replace (inlineCodeRegex, function (fullTag, first, second, third) {
				return codeReplace (fullTag, first, second, third, 'inline');
			});

			// Perform each markdown regular expression on the current paragraph
			for (var r = 0, rl = markdownSearch.length; r < rl; r++) {
				// Replace markdown patterns
				paragraphs[i] = paragraphs[i].replace (markdownSearch[r], markdownReplace[r]);
			}

			// Return the original markdown code with HTML replacement
			paragraphs[i] = paragraphs[i].replace (inlineCodeMarker, function (marker, number) {
				return '<code class="hashover-inline">' + codeMarkers.inline.marks[number] + '</code>';
			});
		}

		// Join paragraphs
		string = paragraphs.join (doubleEOL);

		// Replace code block markers with original markdown code
		string = string.replace (blockCodeMarker, function (marker, number) {
			return '<code>' + codeMarkers.block.marks[number] + '</code>';
		});

		return string;
	}

	// Adds properties to an element
	function addProperties (element, properties)
	{
		element = element || document.createElement ('span');
		properties = properties || {};

		// Add each property to element
		for (var property in properties) {
			if (properties.hasOwnProperty (property) === false) {
				continue;
			}

			// If the property is an object add each item to existing property
			if (!!properties[property] && properties[property].constructor === Object) {
				addProperties (element[property], properties[property]);
				continue;
			}

			element[property] = properties[property];
		}

		return element;
	}

	// Create an element with attributes
	function createElement (tagName, attributes)
	{
		tagName = tagName || 'span';
		attributes = attributes || {};

		// Create element
		var element = document.createElement (tagName);

		// Add properties to element
		element = addProperties (element, attributes);

		return element;
	}

	// Add comment content to HTML template
	function parseComment (comment, parent, collapse, sort, method, popular)
	{
		parent = parent || null;
		collapse = collapse || false;
		sort = sort || false;
		method = method || 'ascending';
		popular = popular || false;

		var permalink = comment.permalink;
		var nameClass = 'hashover-name-plain';
		var template = { permalink: permalink };
		var isReply = (parent !== null);
		var parentPermalink;
		var codeTagCount = 0;
		var codeTags = [];
		var preTagCount = 0;
		var preTags = [];
		var classes = '';
		var replies = '';

		// Text for avatar image alt attribute
		var permatext = permalink.slice (1);
		    permatext = permatext.split ('r');
		    permatext = permatext.pop ();

		// Get parent comment via permalink
		if (isReply === false && permalink.indexOf ('r') > -1) {
			parentPermalink = getParentPermalink (permalink);
			parent = findByPermalink (parentPermalink, PHPContent.comments);
			isReply = (parent !== null);
		}

		// Check if this comment is a popular comment
		if (popular === true) {
			// Remove "-pop" from text for avatar
			permatext = permatext.replace ('-pop', '');
		} else {
			// Check if comment is a reply
			if (isReply === true) {
				// Check that comments are being sorted
				if (!sort || method === 'ascending') {
					// Append class to indicate comment is a reply
					classes += ' hashover-reply';
				}
			}
		}

		// Add avatar image to template
		template.avatar = '<span class="hashover-avatar"><div style="background-image: url(\'' + comment.avatar + '\');"></div></span>';

		if (comment.notice === undefined) {
			var name = comment.name || defaultName;
			var website = comment.website;
			var isTwitter = false;

			// Check if user's name is a Twitter handle
			if (name.charAt (0) === '@') {
				name = name.slice (1);
				nameClass = 'hashover-name-twitter';
				isTwitter = true;
				var nameLength = name.length;

				// Check if Twitter handle is valid length
				if (nameLength > 1 && nameLength <= 30) {
					// Set website to Twitter profile if a specific website wasn't given
					if (website === undefined) {
						website = 'http://twitter.com/' + name;
					}
				}
			}

			// Check whether user gave a website
			if (website !== undefined) {
				if (isTwitter === false) {
					nameClass = 'hashover-name-website';
				}

				// If so, display name as a hyperlink
				var nameLink = '<a href="' + website + '" rel="noopener noreferrer" target="_blank" class="hashover-name-' + permalink + '">' + name + '</a>';
			} else {
				// If not, display name as plain text
				var nameLink = '<span class="hashover-name-' + permalink + '">' + name + '</span>';
			}

			// Construct thread hyperlink
			if (isReply === true) {
				var parentThread = parent.permalink;
				var parentName = parent.name || defaultName;

				// Add thread parent hyperlink to template
				template['thread-link'] = '<a href="#' + parentThread + '" id="hashover-thread-link-' + permalink + '" class="hashover-thread-link" title="返回顶部">回复' + parentName + '</a>';
			}

			if (comment['user-owned'] !== undefined) {
				// Append class to indicate comment is from logged in user
				classes += ' hashover-user-owned';

				// Define "Reply" link with original poster title
				var replyTitle = '您不会通过邮箱获取通知';
				var replyClass = 'hashover-no-email';

				// Add "Edit" hyperlink to template
				template['edit-link'] = '<a href="?hashover-edit=' + permalink + '#hashover-edit-' + permalink + '" id="hashover-edit-link-' + permalink + '" class="hashover-comment-edit" title="编辑你的评论">编辑</a>';
			} else {
				// Check if commenter is subscribed
				if (comment.subscribed === true) {
					// If so, set subscribed title
					var replyTitle = name + ' 将通过邮箱通知';
					var replyClass = 'hashover-has-email';
				} else{
					// If not, set unsubscribed title
					var replyTitle = name + ' 未订阅邮箱通知';
					var replyClass = 'hashover-no-email';
				}
			}

			// Add name HTML to template
			template.name = '<span class="hashover-comment-name ' + nameClass + '">' + nameLink + '</span>';

			// Add date permalink hyperlink to template
			template.date = '<a href="#' + permalink + '" class="hashover-date-permalink" title="Permalink">' + comment.date + '</a>';

			// Add "Reply" hyperlink to template
			template['reply-link'] = '<a href="?hashover-reply=' + permalink + '#hashover-reply-' + permalink + '" id="hashover-reply-link-' + permalink + '" class="hashover-comment-reply ' + replyClass + '" title="回复评论 - ' + replyTitle + '">回复</a>';

			// Add reply count to template
			if (comment.replies !== undefined) {
				template['reply-count'] = comment.replies.length;

				if (template['reply-count'] > 0) {
					if (template['reply-count'] !== 1) {
						template['reply-count'] += ' 条回复';
					} else {
						template['reply-count'] += ' 回复';
					}
				}
			}

			// Add HTML anchor tag to URLs
			var body = comment.body.replace (linkRegex, '<a href="$1" rel="noopener noreferrer" target="_blank">$1</a>');

			// Replace [img] tags with external image placeholder if enabled
			body = body.replace (imageRegex, function (fullURL, url) {
				// Get image extension from URL
				var urlExtension = url.split ('#')[0];
				    urlExtension = urlExtension.split ('?')[0];
				    urlExtension = urlExtension.split ('.');
				    urlExtension = urlExtension.pop ();

				// Check if the image extension is an allowed type
				if (imageExtensions.indexOf (urlExtension) > -1) {
					// If so, create a wrapper element for the embedded image
					var embeddedImage = createElement ('span', {
						className: 'hashover-embedded-image-wrapper'
					});

					// Append an image tag to the embedded image wrapper
					embeddedImage.appendChild (createElement ('img', {
						className: 'hashover-embedded-image',
						src: url,
						alt: 'External Image',

						dataset: {
							placeholder: imagePlaceholder,
							url: url
						}
					}));

					// And return the embedded image HTML
					return embeddedImage.outerHTML;
				}

				// Convert image URL into an anchor tag
				return '<a href="' + url + '" rel="noopener noreferrer" target="_blank">' + url + '</a>';
			});

			// Parse markdown in comment
			body = parseMarkdown (body);

			// Check for code tags
			if (codeOpenRegex.test (body) === true) {
				// Replace code tags with marker text
				body = body.replace (codeTagRegex, function (fullTag, openTag, innerHTML, closeTag) {
					var codeMarker = openTag + 'CODE_TAG[' + codeTagCount + ']' + closeTag;

					codeTags[codeTagCount] = EOLTrim (innerHTML);
					codeTagCount++;

					return codeMarker;
				});
			}

			// Check for pre tags
			if (preOpenRegex.test (body) === true) {
				// Replace pre tags with marker text
				body = body.replace (preTagRegex, function (fullTag, openTag, innerHTML, closeTag) {
					var preMarker = openTag + 'PRE_TAG[' + preTagCount + ']' + closeTag;

					preTags[preTagCount] = EOLTrim (innerHTML);
					preTagCount++;

					return preMarker;
				});
			}

			// Check for various multi-line tags
			for (var trimTag in trimTagRegexes) {
				if (trimTagRegexes.hasOwnProperty (trimTag) === true
				    && trimTagRegexes[trimTag]['test'].test (body) === true)
				{
					// Trim whitespace
					body = body.replace (trimTagRegexes[trimTag]['replace'], tagTrimmer);
				}
			}

			// Break comment into paragraphs
			var paragraphs = body.split (paragraphRegex);
			var pdComment = '';

			// Wrap comment in paragraph tag
			// Replace single line breaks with break tags
			for (var i = 0, il = paragraphs.length; i < il; i++) {
				pdComment += '<p>' + paragraphs[i].replace (lineRegex, '<br>') + '</p>' + serverEOL;
			}

			// Replace code tag markers with original code tag HTML
			if (codeTagCount > 0) {
				pdComment = pdComment.replace (codeTagMarkerRegex, function (marker, number) {
					return codeTags[number];
				});
			}

			// Replace pre tag markers with original pre tag HTML
			if (preTagCount > 0) {
				pdComment = pdComment.replace (preTagMarkerRegex, function (marker, number) {
					return preTags[number];
				});
			}

			// Add comment data to template
			template.comment = pdComment;
		} else {
			// Append notice class
			classes += ' hashover-notice ' + comment['notice-class'];

			// Add notice to template
			template.comment = comment.notice;

			// Add name HTML to template
			template.name = '<span class="hashover-comment-name ' + nameClass + '">' + comment.title + '</span>';
		}

		// Comment HTML template
		var html = '' + (template['avatar'] || '') + '\n';
		    html += '<div class="hashover-balloon">\n';
		    html += '\t<div class="hashover-header">\n';
		    html += '\t\t' + (template['name'] || '') + ' ' + (template['thread-link'] || '') + '\n';
		    html += '\t</div>\n';
		    html += '\t<div id="hashover-content-' + (template['permalink'] || '') + '" class="hashover-content">\n';
		    html += '\t\t' + (template['comment'] || '') + '\n';
		    html += '\t</div>\n';
		    html += '\t<span id="hashover-placeholder-edit-form-' + (template['permalink'] || '') + '"></span>\n';
		    html += '\t<div id="hashover-footer-' + (template['permalink'] || '') + '" class="hashover-footer">\n';
		    html += '\t\t<span class="hashover-date">\n';
		    html += '\t\t\t' + (template['date'] || '') + '&nbsp;\n';
		    html += '\t\t\t' + (template['like-count'] || '') + '&nbsp;\n';
		    html += '\t\t\t' + (template['dislike-count'] || '') + '\n';
		    html += '\t\t</span>\n';
		    html += '\t\t<span class="hashover-buttons">\n';
		    html += '\t\t\t' + (template['like-link'] || '') + '\n';
		    html += '\t\t\t' + (template['dislike-link'] || '') + '\n';
		    html += '\t\t\t' + (template['edit-link'] || '') + '\n';
		    html += '\t\t\t' + (template['reply-link'] || '') + '\n';
		    html += '\t\t</span>\n';
		    html += '\t</div>\n';
		    html += '</div>\n';
		    html += '<span id="hashover-placeholder-reply-form-' + (template['permalink'] || '') + '"></span>\n';

		// Recursively parse replies
		if (comment.replies !== undefined) {
			for (var reply = 0, total = comment.replies.length; reply < total; reply++) {
				replies += parseComment (comment.replies[reply], comment, collapse);
			}
		}

		return '<div id="' + permalink + '" class="hashover-comment' + classes + '">' + html + replies + '</div>';
	}

	// Generate file from permalink
	function fileFromPermalink (permalink)
	{
		var file = permalink.slice (1);
		    file = file.replace (/r/g, '-');
		    file = file.replace ('-pop', '');

		return file;
	}

	// Change and hyperlink, like "Edit" or "Reply", into a "Cancel" hyperlink
	function cancelSwitcher (form, link, wrapper, permalink)
	{
		// Initial state properties of hyperlink
		var reset = {
			textContent: link.textContent,
			title: link.title,
			onclick: link.onclick
		};

		function linkOnClick ()
		{
			// Remove fields from form wrapper
			wrapper.textContent = '';

			// Reset button
			link.textContent = reset.textContent;
			link.title = reset.title;
			link.onclick = reset.onclick;

			return false;
		}

		// Change hyperlink to "Cancel" hyperlink
		link.textContent = locale.cancel;
		link.title = locale.cancel;

		// This resets the "Cancel" hyperlink to initial state onClick
		link.onclick = linkOnClick;

		// Get "Cancel" button
		var cancelButtonId = 'hashover-' + form + '-cancel-' + permalink;
		var cancelButton = getElement (cancelButtonId, true);

		// Attach event listeners to "Cancel" button
		cancelButton.onclick = linkOnClick;
	}

	// Returns false if key event is the enter key
	function enterCheck (event)
	{
		return (event.keyCode === 13) ? false : true;
	}

	// Prevents enter key on inputs from submitting form
	function preventSubmit (form)
	{
		// Get login info inputs
		var infoInputs = form.getElementsByClassName ('hashover-input-info');

		// Set enter key press to return false
		for (var i = 0, il = infoInputs.length; i < il; i++) {
			infoInputs[i].onkeypress = enterCheck;
		}
	}

	// Check whether browser has classList support
	if (document.documentElement.classList) {
		// If so, wrap relevant functions
		// classList.contains () method
		var containsClass = function (element, className)
		{
			return element.classList.contains (className);
		};

		// classList.add () method
		var addClass = function (element, className)
		{
			element.classList.add (className);
		};

		// classList.remove () method
		var removeClass = function (element, className)
		{
			element.classList.remove (className);
		};
	} else {
		// If not, define fallback functions
		// classList.contains () method
		var containsClass = function (element, className)
		{
			if (!element || !element.className) {
				return false;
			}

			var regex = new RegExp ('(^|\\s)' + className + '(\\s|$)');
			return regex.test (element.className);
		};

		// classList.add () method
		var addClass = function (element, className)
		{
			if (!element) {
				return false;
			}

			if (!containsClass (element, className)) {
				element.className += (element.className ? ' ' : '') + className;
			}
		};

		// classList.remove () method
		var removeClass = function (element, className)
		{
			if (!element || !element.className) {
				return false;
			}

			var regex = new RegExp ('(^|\\s)' + className + '(\\s|$)', 'g');
			element.className = element.className.replace (regex, '$2');
		};
	}

	// Handle message element(s)
	function showMessage (message, permalink, error, isReply, isEdit)
	{
		permalink = permalink || '';
		error = error || true;
		isReply = isReply || false;
		isEdit = isEdit || false;

		var element;

		// Decide which message element to use
		if (isEdit === true) {
			element = getElement ('hashover-edit-message-' + permalink, true);
		} else {
			if (isReply !== true) {
				element = getElement ('hashover-message', true);
			} else {
				element = getElement ('hashover-reply-message-' + permalink, true);
			}
		}

		if (message !== undefined && message !== '') {
			// Add message text to element
			element.textContent = message;

			// Add class to indicate message is an error if set
			if (error === true) {
				addClass (element, 'hashover-message-error');
			}
		}

		// Add class to indicate message element is open
		addClass (element, 'hashover-message-open');

		// Add the comment to message counts
		if (messageCounts[permalink] === undefined) {
			messageCounts[permalink] = 0;
		}

		// Add timeout to close message element after 10 seconds
		setTimeout (function () {
			if (messageCounts[permalink] <= 1) {
				removeClass (element, 'hashover-message-open');
				removeClass (element, 'hashover-message-error');
			}

			// Decrease count of open message timeouts
			messageCounts[permalink]--;
		}, 10000);

		// Increase count of open message timeouts
		messageCounts[permalink]++;
	}

	// Handles display of various warnings when user attempts to post or login
	function emailValidator (form, subscribe, permalink, isReply, isEdit)
	{
		if (form.email === undefined) {
			return true;
		}

		// Whether the e-mail form is empty
		if (form.email.value === '') {
			// Return true if user unchecked the subscribe checkbox
			if (getElement (subscribe, true).checked === false) {
				return true;
			}

			// If so, warn the user that they won't receive reply notifications
			if (confirm ('如果不填写邮箱，你将不会收到对你评论的回复通知。') === false) {
				form.email.focus ();
				return false;
			}
		} else {
			var message;
			var emailRegex = /\S+@\S+/;

			// If not, check if the e-mail is valid
			if (emailRegex.test (form.email.value) === false) {
				// Return true if user unchecked the subscribe checkbox
				if (getElement (subscribe, true).checked === false) {
					form.email.value = '';
					return true;
				}

				message = '输入的邮箱是无效的。';
				showMessage (message, permalink, true, isReply, isEdit);
				form.email.focus ();

				return false;
			}
		}

		return true;
	}

	// Validate a comment form e-mail field
	function validateEmail (permalink, form, isReply, isEdit)
	{
		permalink = permalink || null;
		isReply = isReply || false;
		isEdit = isEdit || false;

		var subscribe;

		// Check whether comment is an edit
		if (isEdit === true) {
			// If it is, validate edit form e-mail
			subscribe = 'hashover-subscribe-' + permalink;
		} else {
			// If it is not, validate as primary or reply
			if (isReply !== true) {
				// Validate primary form e-mail
				subscribe = 'hashover-subscribe';
			} else {
				// Validate reply form e-mail
				subscribe = 'hashover-subscribe-' + permalink;
			}
		}

		// Validate form fields
		return emailValidator (form, subscribe, permalink, isReply, isEdit);
	}

	// Validate a comment form
	function commentValidator (form, skipComment)
	{
		skipComment = skipComment || false;

		var fieldNeeded = '「%s」是必须要填写的。';

		// Check each input field for if they are required
		for (var field in fieldOptions) {
			// Skip other people's prototypes
			if (fieldOptions.hasOwnProperty (field) !== true) {
				continue;
			}

			// Check if the field is required, and that the input exists
			if (fieldOptions[field] === 'required' && form[field] !== undefined) {
				// Check if it has a value
				if (form[field].value === '') {
					// If not, add a class indicating a failed post
					addClass (form[field], 'hashover-emphasized-input');

					// Focus the input
					form[field].focus ();

					// Return error message to display to the user
					return fieldNeeded.replace ('%s', locale[field]);
				}

				// Remove class indicating a failed post
				removeClass (form[field], 'hashover-emphasized-input');
			}
		}

		// Check if a comment was given
		if (skipComment !== true && form.comment.value === '') {
			// If not, add a class indicating a failed post
			addClass (form.comment, 'hashover-emphasized-input');

			// Focus the comment textarea
			form.comment.focus ();

			// Return a error message to display to the user
			return '您未能输入正确的评论。使用下面的表格。';
		}

		return true;
	}

	// Validate required comment credentials
	function validateComment (skipComment, form, permalink, isReply, isEdit)
	{
		skipComment = skipComment || false;
		permalink = permalink || null;
		isReply = isReply || false;
		isEdit = isEdit || false;

		// Validate comment form
		var message = commentValidator (form, skipComment);

		// Display the validator's message
		if (message !== true) {
			showMessage (message, permalink, true, isReply, isEdit);
			return false;
		}

		// Validate e-mail if user isn't logged in or is editing
		if (userIsLoggedIn === false || isEdit === true) {
			// Return false on any failure
			if (validateEmail (permalink, form, isReply, isEdit) === false) {
				return false;
			}
		}

		return true;
	}

	// For posting comments, both traditionally and via AJAX
	function postComment (destination, form, button, callback, permalink, close, isReply, isEdit)
	{
		permalink = permalink || '';
		close = close || null;
		isReply = isReply || false;
		isEdit = isEdit || false;

		// Return false if comment is invalid
		if (validateComment (false, form, permalink, isReply, isEdit) === false) {
			return false;
		}

		// Disable button
		setTimeout (function () {
			button.disabled = true;
		}, 500);

		var httpRequest = new XMLHttpRequest ();
		var formElements = form.elements;
		var elementsLength = formElements.length;
		var queries = [];

		// Get all form input names and values
		for (var i = 0; i < elementsLength; i++) {
			// Skip login/logout input
			if (formElements[i].name === 'login' || formElements[i].name === 'logout') {
				continue;
			}

			// Skip unchecked checkboxes
			if (formElements[i].type === 'checkbox' && formElements[i].checked !== true) {
				continue;
			}

			// Skip delete input
			if (formElements[i].name === 'delete') {
				continue;
			}

			// Add query to queries array
			queries.push (formElements[i].name + '=' + encodeURIComponent (formElements[i].value));
		}

		// Add AJAX query to queries array
		queries.push ('ajax=yes');

		// Handle AJAX request return data
		httpRequest.onreadystatechange = function ()
		{
			// Do nothing if request wasn't successful in a meaningful way
			if (this.readyState !== 4 || this.status !== 200) {
				return;
			}

			// Parse AJAX response as JSON
			var json = JSON.parse (this.responseText);
			var scrollToElement;

			// Check if JSON includes a comment
			if (json.comment !== undefined) {
				// If so, execute callback function
				callback (json, permalink, destination, isReply);

				// Execute callback function if one was provided
				if (close !== null) {
					close ();
				}

				// Scroll comment into view
				scrollToElement = getElement (json.comment.permalink, true);
				scrollToElement.scrollIntoView ({ behavior: 'smooth' });

				// Clear form
				form.comment.value = '';
			} else {
				// If not, display the message return instead
				showMessage (json.message, permalink, (json.type === 'error'), isReply, isEdit);
				return false;
			}

			// Re-enable button on success
			setTimeout (function () {
				button.disabled = false;
			}, 1000);
		};

		// Send request
		httpRequest.open ('POST', form.action, true);
		httpRequest.setRequestHeader ('Content-type', 'application/x-www-form-urlencoded');
		httpRequest.send (queries.join ('&'));

		// Re-enable button after 20 seconds
		setTimeout (function () {
			// Abort unfinish request
			httpRequest.abort ();

			// Re-enable button
			button.disabled = false;
		}, 20000);

		return false;
	}

	// Converts an HTML string to DOM NodeList
	function HTMLToNodeList (html)
	{
		return createElement ('div', { innerHTML: html }).childNodes;
	}

	// Increase comment counts
	function incrementCounts (isReply)
	{
		// Count top level comments
		if (isReply === false) {
			primaryCount++;
		}

		// Increase all count
		totalCount++;
	}

	// For adding new comments to comments array
	function addComments (comment, isReply, index)
	{
		isReply = isReply || false;
		index = index || null;

		// Check that comment is not a reply
		if (isReply !== true) {
			// If so, add to primary comments
			if (index !== null) {
				PHPContent.comments.splice (index, 0, comment);
				return;
			}

			PHPContent.comments.push (comment);
			return;
		}

		// If not, fetch parent comment
		var parentPermalink = getParentPermalink (comment.permalink);
		var parent = findByPermalink (parentPermalink, PHPContent.comments);

		// Check if comment has replies
		if (parent !== null && parent.replies !== undefined) {
			// If so, add comment to reply array
			if (index !== null) {
				parent.replies.splice (index, 0, comment);
				return;
			}

			parent.replies.push (comment);
			return;
		}

		// If not, create reply array
		parent.replies = [comment];
	}

	// For posting comments
	AJAXPost = function (json, permalink, destination, isReply)
	{
		// If there aren't any comments, replace first comment message
		if (totalCount === 0) {
			PHPContent.comments[0] = json.comment;
			destination.innerHTML = parseComment (json.comment);
		} else {
			// Add comment to comments array
			addComments (json.comment, isReply);

			// Create div element for comment
			var commentNode = HTMLToNodeList (parseComment (json.comment));

			// Append comment to parent element
			if (streamMode === true && permalink.split('r').length > streamDepth) {
				destination.parentNode.insertBefore (commentNode[0], destination.nextSibling);
			} else {
				destination.appendChild (commentNode[0]);
			}
		}

		// Add controls to the new comment
		addControls (json.comment);

		// Update comment count
		getElement ('hashover-count').textContent = json.count;
		incrementCounts (isReply);
	};

	// For editing comments
	AJAXEdit = function (json, permalink, destination, isReply)
	{
		// Get old comment element nodes
		var comment = getElement (permalink, true);
		var oldNodes = comment.childNodes;
		var oldComment = findByPermalink (permalink, PHPContent.comments);

		// Get new comment element nodes
		var newNodes = HTMLToNodeList (parseComment (json.comment));
		    newNodes = newNodes[0].childNodes;

		// Replace old comment with edited comment
		for (var i = 0, il = newNodes.length; i < il; i++) {
			if (typeof (oldNodes[i]) === 'object'
			    && typeof (newNodes[i]) === 'object')
			{
				comment.replaceChild (newNodes[i], oldNodes[i]);
			}
		}

		// Add controls back to the comment
		addControls (json.comment);

		// Update old in array comment with edited comment
		for (var attribute in json.comment) {
			oldComment[attribute] = json.comment[attribute];
		}
	};

	// Displays reply form
	function hashoverReply (permalink)
	{
		// Get reply link element
		var link = getElement ('hashover-reply-link-' + permalink, true);

		// Get file
		var file = fileFromPermalink (permalink);

		// Create reply form element
		var form = createElement ('form', {
			id: 'hashover-reply-' + permalink,
			className: 'hashover-reply-form',
			method: 'post',
			action: httpScripts + '/postcomments.php'
		});

		var formHTML = '<div class="hashover-balloon">\n';
		    formHTML += '\t<div class="hashover-avatar-image">\n';
		    formHTML += '\t\t<div style="background-image: url(\'/hashover/images/first-comment.svg\');"></div>\n';
		    formHTML += '\t</div>\n';
		    formHTML += '\t<div class="hashover-inputs">\n';
		    formHTML += '\t\t<div class="hashover-input-cell">\n';
		    formHTML += '\t\t\t<div class="hashover-name-input">\n';
		    formHTML += '\t\t\t\t<input id="hashover-main-name" class="hashover-input-info" type="text" name="name" title="昵称 (可选)" value="idken" placeholder="昵称">\n';
		    formHTML += '\t\t\t</div>\n';
		    formHTML += '\t\t</div>\n';
		    formHTML += '\t\t<div class="hashover-input-cell">\n';
		    formHTML += '\t\t\t<div class="hashover-email-input">\n';
		    formHTML += '\t\t\t\t<input id="hashover-main-email" class="hashover-input-info" type="email" name="email" title="邮箱 (可选，用于接收通知邮件)" value="asdad@asaa.com" placeholder="邮箱">\n';
		    formHTML += '\t\t\t</div>\n';
		    formHTML += '\t\t</div>\n';
		    formHTML += '\t\t<div class="hashover-input-cell">\n';
		    formHTML += '\t\t\t<div class="hashover-website-input">\n';
		    formHTML += '\t\t\t\t<input id="hashover-main-website" class="hashover-input-info" type="url" name="website" title="网址 (可选)" value="" placeholder="网址">\n';
		    formHTML += '\t\t\t</div>\n';
		    formHTML += '\t\t</div>\n';
		    formHTML += '\t</div>\n';
		    formHTML += '\t<textarea id="hashover-reply-comment" class="hashover-textarea hashover-reply-textarea" cols="62" name="comment" rows="5" title="接受HTML: &lt;b&gt;，&lt;u&gt;，&lt;i&gt;，&lt;s&gt;，&lt;big&gt;，&lt;em&gt;，&lt;small&gt;，&lt;strong&gt;，&lt;sub&gt;，&lt;sup&gt;，&lt;pre&gt;，&lt;ul&gt;，&lt;ol&gt;，&lt;li&gt;，&lt;blockquote&gt;，&lt;code&gt;转义HTML，网址自动成为链接，[img]URL在这里[/img] 将显示外部图像。" placeholder="请在这里输入你要回复的内容..."></textarea>\n';
		    formHTML += '\t<input type="hidden" name="url" value="http://tildehash.com/comments.html">\n';
		    formHTML += '\t<input type="hidden" name="title" value="HashOver ~ Free and Open Source PHP Comment System">\n';
		    formHTML += '\t<input type="hidden" name="reply-to" value="' + file + '">\n';
		    formHTML += '\t<div id="hashover-reply-message-' + permalink + '" class="hashover-message"></div>\n';
		    formHTML += '\t<div class="hashover-form-footer">\n';
		    formHTML += '\t\t<label for="hashover-subscribe-' + permalink + '" class="hashover-reply-label" title="订阅电子邮件通知">\n';
		    formHTML += '\t\t\t<input id="hashover-subscribe-' + permalink + '" type="checkbox" name="subscribe" checked="true">\n';
		    formHTML += '\t\t\t有回复通知我\n';
		    formHTML += '\t\t</label>\n';
		    formHTML += '\t\t<span class="hashover-form-buttons">\n';
		    formHTML += '\t\t\t<a href="/comments.html#' + permalink + '" id="hashover-reply-cancel-' + permalink + '" class="hashover-submit hashover-reply-cancel" title="取消">取消</a>\n';
		    formHTML += '\t\t\t<input id="hashover-reply-post-' + permalink + '" class="hashover-submit hashover-reply-post" type="submit" name="post" value="发表回复" title="发表回复">\n';
		    formHTML += '\t\t</span>\n';
		    formHTML += '\t</div>\n';
		    formHTML += '</div>\n';

		// Place reply fields into form
		form.innerHTML = formHTML;

		// Prevent input submission
		preventSubmit (form);

		// Add form to page
		var replyForm = getElement ('hashover-placeholder-reply-form-' + permalink, true);
		    replyForm.appendChild (form);

		// Change "Reply" link to "Cancel" link
		cancelSwitcher ('reply', link, replyForm, permalink);

		// Attach event listeners to "Post Reply" button
		var postReply = getElement ('hashover-reply-post-' + permalink, true);

		// Get the element of comment being replied to
		var destination = getElement (permalink, true);

		// Onclick
		postReply.onclick = function ()
		{
			return postComment (destination, form, this, AJAXPost, permalink, link.onclick, true, false);
		};

		// Onsubmit
		postReply.onsubmit = function ()
		{
			return postComment (destination, form, this, AJAXPost, permalink, link.onclick, true, false);
		};

		// Focus comment field
		form.comment.focus ();

		return true;
	}

	// Displays edit form
	function hashoverEdit (comment)
	{
		if (comment['user-owned'] !== true) {
			return false;
		}

		// Get permalink from comment JSON object
		var permalink = comment.permalink;

		// Get edit link element
		var link = getElement ('hashover-edit-link-' + permalink, true);

		// Get file
		var file = fileFromPermalink (permalink);

		// Get name and website
		var name = comment.name || '';
		var website = comment.website || '';

		// Get and clean comment body
		var body = comment.body.replace (linkRegex, '$1');

		// Create edit form element
		var form = createElement ('form', {
			id: 'hashover-edit-' + permalink,
			className: 'hashover-edit-form',
			method: 'post',
			action: httpScripts + '/postcomments.php'
		});

		var formHTML = '<div class="hashover-title hashover-dashed-title">编辑评论</div>\n';
		    formHTML += '<div class="hashover-inputs">\n';
		    formHTML += '\t<div class="hashover-input-cell">\n';
		    formHTML += '\t\t<div class="hashover-name-input">\n';
		    formHTML += '\t\t\t<input id="hashover-main-name" class="hashover-input-info" type="text" name="name" title="昵称 (可选)" value="' + name + '" placeholder="昵称">\n';
		    formHTML += '\t\t</div>\n';
		    formHTML += '\t</div>\n';
		    formHTML += '\t<div class="hashover-input-cell">\n';
		    formHTML += '\t\t<div class="hashover-email-input">\n';
		    formHTML += '\t\t\t<input id="hashover-main-email" class="hashover-input-info" type="email" name="email" title="邮箱 (可选，用于接收通知邮件)" value="asdad@asaa.com" placeholder="邮箱">\n';
		    formHTML += '\t\t</div>\n';
		    formHTML += '\t</div>\n';
		    formHTML += '\t<div class="hashover-input-cell">\n';
		    formHTML += '\t\t<div class="hashover-website-input">\n';
		    formHTML += '\t\t\t<input id="hashover-main-website" class="hashover-input-info" type="url" name="website" title="网址 (可选)" value="' + website + '" placeholder="网址">\n';
		    formHTML += '\t\t</div>\n';
		    formHTML += '\t</div>\n';
		    formHTML += '</div>\n';
		    formHTML += '<textarea id="hashover-edit-comment" class="hashover-textarea hashover-edit-textarea" cols="62" name="comment" rows="10" title="接受HTML: &lt;b&gt;，&lt;u&gt;，&lt;i&gt;，&lt;s&gt;，&lt;big&gt;，&lt;em&gt;，&lt;small&gt;，&lt;strong&gt;，&lt;sub&gt;，&lt;sup&gt;，&lt;pre&gt;，&lt;ul&gt;，&lt;ol&gt;，&lt;li&gt;，&lt;blockquote&gt;，&lt;code&gt;转义HTML，网址自动成为链接，[img]URL在这里[/img] 将显示外部图像。">' + body + '</textarea>\n';
		    formHTML += '<input type="hidden" name="url" value="http://tildehash.com/comments.html">\n';
		    formHTML += '<input type="hidden" name="title" value="HashOver ~ Free and Open Source PHP Comment System">\n';
		    formHTML += '<input type="hidden" name="file" value="' + file + '">\n';
		    formHTML += '<div id="hashover-edit-message-' + permalink + '" class="hashover-message"></div>\n';
		    formHTML += '<div class="hashover-form-footer">\n';
		    formHTML += '\t<label for="hashover-subscribe-' + permalink + '" class="hashover-edit-label" title="订阅电子邮件通知">\n';
		    formHTML += '\t\t<input id="hashover-subscribe-' + permalink + '" type="checkbox" name="subscribe" checked="true">\n';
		    formHTML += '\t\t有回复通知我\n';
		    formHTML += '\t</label>\n';
		    formHTML += '\t<span class="hashover-form-buttons">\n';
		    formHTML += '\t\t<a href="/comments.html#' + permalink + '" id="hashover-edit-cancel-' + permalink + '" class="hashover-submit hashover-edit-cancel" title="取消">取消</a>\n';
		    formHTML += '\t\t<input id="hashover-edit-post-' + permalink + '" class="hashover-submit hashover-edit-post" type="submit" name="edit" value="保存编辑" title="保存编辑">\n';
		    formHTML += '\t\t<input id="hashover-edit-delete-' + permalink + '" class="hashover-submit hashover-edit-delete" type="submit" name="delete" value="删除" title="删除">\n';
		    formHTML += '\t</span>\n';
		    formHTML += '</div>\n';

		// Place edit form fields into form
		form.innerHTML = formHTML;

		// Prevent input submission
		preventSubmit (form);

		// Add edit form to page
		var editForm = getElement ('hashover-placeholder-edit-form-' + permalink, true);
		    editForm.appendChild (form);

		// Set status dropdown menu option to comment status
		ifElement ('hashover-edit-status-' + permalink, function (status) {
			if (comment.status !== undefined) {
				status.selectedIndex = commentStatuses.indexOf (comment.status);
			}
		});

		// Blank out password field
		setTimeout (function () {
			if (form.password !== undefined) {
				form.password.value = '';
			}
		}, 100);

		// Uncheck subscribe checkbox if user isn't subscribed
		if (comment.subscribed !== true) {
			getElement ('hashover-subscribe-' + permalink, true).checked = null;
		}

		// Displays onClick confirmation dialog for comment deletion
		getElement ('hashover-edit-delete-' + permalink, true).onclick = function ()
		{
			return confirm ('你确定要删除此评论吗？');
		};

		// Change "Edit" link to "Cancel" link
		cancelSwitcher ('edit', link, editForm, permalink);

		// Attach event listeners to "Save Edit" button
		var saveEdit = getElement ('hashover-edit-post-' + permalink, true);

		// Get the element of comment being replied to
		var destination = getElement (permalink, true);

		// Onclick
		saveEdit.onclick = function ()
		{
			return postComment (destination, form, this, AJAXEdit, permalink, link.onclick, false, true);
		};

		// Onsubmit
		saveEdit.onsubmit = function ()
		{
			return postComment (destination, form, this, AJAXEdit, permalink, link.onclick, false, true);
		};

		return false;
	}

	// Callback to close the embedded image
	//function closeEmbeddedImage (image) {
		// Reset source
		//image.src = image.dataset.placeholder;

		// Reset title
		//image.title = locale.externalImageTip;

		// Remove loading class from wrapper
		//removeClass (image.parentNode, 'hashover-loading');
	//}

	// Onclick callback function for embedded images
	//function embeddedImageCallback ()
	//{
		// If embedded image is open, close it and return false
		//if (this.src === this.dataset.url) {
			//closeEmbeddedImage (this);
			//return false;
		//}

		// Set title
		//this.title = '载入中…';

		// Add loading class to wrapper
		//addClass (this.parentNode, 'hashover-loading');

		// Change title and remove load event handler once image is loaded
		//this.onload = function ()
		//{
			//this.title = '点击关闭';
			//this.onload = null;

			// Remove loading class from wrapper
		//	removeClass (this.parentNode, 'hashover-loading');
	//	};

		// Close embedded image if any error occurs
	//	this.onerror = function ()
	//	{
	//		closeEmbeddedImage (this);
	//	};

		// Set placeholder image to embedded source
	//	this.src = this.dataset.url;
	//}

	// Changes Element.textContent onmouseover and reverts onmouseout
	function mouseOverChanger (element, over, out)
	{
		if (over === null || out === null) {
			element.onmouseover = null;
			element.onmouseout = null;

			return false;
		}

		element.onmouseover = function ()
		{
			this.textContent = over;
		};

		element.onmouseout = function ()
		{
			this.textContent = out;
		};
	}

	// Add various events to various elements in each comment
	function addControls (json, popular)
	{
		function stepIntoReplies ()
		{
			if (json.replies !== undefined) {
				for (var reply = 0, total = json.replies.length; reply < total; reply++) {
					addControls (json.replies[reply]);
				}
			}
		}

		if (json.notice !== undefined) {
			stepIntoReplies ();
			return false;
		}

		// Get permalink from JSON object
		var permalink = json.permalink;

		// Get embedded image elements
		var embeddedImgs = document.getElementsByClassName ('hashover-embedded-image');

		// Set onclick functions for external images
		for (var i = 0, il = embeddedImgs.length; i < il; i++) {
			//embeddedImgs[i].onclick = embeddedImageCallback;
		}

		// Get reply link of comment
		ifElement ('hashover-reply-link-' + permalink, function (replyLink) {
			// Add onClick event to "Reply" hyperlink
			replyLink.onclick = function ()
			{
				hashoverReply (permalink);
				return false;
			};
		});

		// Check if logged in user owns the comment
		if (json['user-owned'] === true) {
			ifElement ('hashover-edit-link-' + permalink, function (editLink) {
				// Add onClick event to "Edit" hyperlinks
				editLink.onclick = function ()
				{
					hashoverEdit (json);
					return false;
				};
			});
		}

		// Recursively execute this function on replies
		stepIntoReplies ();
	}

	// Returns a clone of an object
	function cloneObject (object)
	{
		return JSON.parse (JSON.stringify (object));
	}

	// "Flatten" the comments object
	function getAllComments (comments)
	{
		var commentsCopy = cloneObject (comments);
		var output = [];

		function descend (comment)
		{
			output.push (comment);

			if (comment.replies !== undefined) {
				for (var reply = 0, total = comment.replies.length; reply < total; reply++) {
					descend (comment.replies[reply]);
				}

				delete comment.replies;
			}
		}

		for (var comment = 0, total = commentsCopy.length; comment < total; comment++) {
			descend (commentsCopy[comment]);
		}

		return output;
	}

	// Run all comments in array data through parseComment function
	function parseAll (comments, element, collapse, popular, sort, method)
	{
		popular = popular || false;
		sort = sort || false;
		method = method || 'ascending';

		var commentHTML = '';

		// Parse every comment
		for (var comment = 0, total = comments.length; comment < total; comment++) {
			commentHTML += parseComment (comments[comment], null, collapse, sort, method, popular);
		}

		// Add comments to element's innerHTML
		if ('insertAdjacentHTML' in element) {
			element.insertAdjacentHTML ('beforeend', commentHTML);
		} else {
			element.innerHTML = commentHTML;
		}

		// Add control events
		for (var comment = 0, total = comments.length; comment < total; comment++) {
			addControls (comments[comment]);
		}
	}

	// Comment sorting
	function sortComments (method)
	{
		var tmpArray;
		var sortArray;

		function replyPropertySum (comment, callback)
		{
			var sum = 0;

			if (comment.replies !== undefined) {
				for (var i = 0, il = comment.replies.length; i < il; i++) {
					sum += replyPropertySum (comment.replies[i], callback);
				}
			}

			sum += callback (comment);

			return sum;
		}

		function replyCounter (comment)
		{
			return (comment.replies) ? comment.replies.length : 0;
		}

		function netLikes (comment)
		{
			var likes = comment.likes || 0;
			var dislikes = comment.dislikes || 0;

			return likes - dislikes;
		}

		// Sort methods
		switch (method) {
			case 'descending': {
				tmpArray = getAllComments (PHPContent.comments);
				sortArray = tmpArray.reverse ();
				break;
			}

			case 'by-date': {
				sortArray = getAllComments (PHPContent.comments).sort (function (a, b) {
					if (a['sort-date'] === b['sort-date']) {
						return 1;
					}

					return b['sort-date'] - a['sort-date'];
				});

				break;
			}

			case 'by-likes': {
				sortArray = getAllComments (PHPContent.comments).sort (function (a, b) {
					a.likes = a.likes || 0;
					b.likes = b.likes || 0;
					a.dislikes = a.dislikes || 0;
					b.dislikes = b.dislikes || 0;

					return (b.likes - b.dislikes) - (a.likes - a.dislikes);
				});

				break;
			}

			case 'by-replies': {
				tmpArray = cloneObject (PHPContent.comments);

				sortArray = tmpArray.sort (function (a, b) {
					var ac = (!!a.replies) ? a.replies.length : 0;
					var bc = (!!b.replies) ? b.replies.length : 0;

					return bc - ac;
				});

				break;
			}

			case 'by-discussion': {
				tmpArray = cloneObject (PHPContent.comments);

				sortArray = tmpArray.sort (function (a, b) {
					var replyCountA = replyPropertySum (a, replyCounter);
					var replyCountB = replyPropertySum (b, replyCounter);

					return replyCountB - replyCountA;
				});

				break;
			}

			case 'by-popularity': {
				tmpArray = cloneObject (PHPContent.comments);

				sortArray = tmpArray.sort (function (a, b) {
					var likeCountA = replyPropertySum (a, netLikes);
					var likeCountB = replyPropertySum (b, netLikes);

					return likeCountB - likeCountA;
				});

				break;
			}

			case 'by-name': {
				tmpArray = getAllComments (PHPContent.comments);

				sortArray = tmpArray.sort (function (a, b) {
					var nameA = (a.name || defaultName).toLowerCase ();
					var nameB = (b.name || defaultName).toLowerCase ();

					nameA = (nameA.charAt (0) === '@') ? nameA.slice (1) : nameA;
					nameB = (nameB.charAt (0) === '@') ? nameB.slice (1) : nameB;

					if (nameA > nameB) {
						return 1;
					}

					if (nameA < nameB) {
						return -1;
					}

					return 0;
				});

				break;
			}

			case 'threaded-descending': {
				tmpArray = cloneObject (PHPContent.comments);
				sortArray = tmpArray.reverse ();
				break;
			}

			case 'threaded-by-date': {
				tmpArray = cloneObject (PHPContent.comments);

				sortArray = tmpArray.sort (function (a, b) {
					if (a['sort-date'] === b['sort-date']) {
						return 1;
					}

					return b['sort-date'] - a['sort-date'];
				});

				break;
			}

			case 'threaded-by-likes': {
				tmpArray = cloneObject (PHPContent.comments);

				sortArray = tmpArray.sort (function (a, b) {
					a.likes = a.likes || 0;
					b.likes = b.likes || 0;
					a.dislikes = a.dislikes || 0;
					b.dislikes = b.dislikes || 0;

					return (b.likes - b.dislikes) - (a.likes - a.dislikes);
				});

				break;
			}

			case 'threaded-by-name': {
				tmpArray = cloneObject (PHPContent.comments);

				sortArray = tmpArray.sort (function (a, b) {
					var nameA = (a.name || defaultName).toLowerCase ();
					var nameB = (b.name || defaultName).toLowerCase ();

					nameA = (nameA.charAt (0) === '@') ? nameA.slice (1) : nameA;
					nameB = (nameB.charAt (0) === '@') ? nameB.slice (1) : nameB;

					if (nameA > nameB) {
						return 1;
					}

					if (nameA < nameB) {
						return -1;
					}

					return 0;
				});

				break;
			}

			default: {
				sortArray = PHPContent.comments;
				break;
			}
		}

		parseAll (sortArray, sortDiv, false, false, true, method);
	}

	// Check if comment theme stylesheet is already in page head
	if (typeof (document.querySelector) === 'function') {
		appendCSS = !document.querySelector ('link[href="' + themeCSS + '"]');
	} else {
		// Fallback for old web browsers without querySelector
		var links = head.getElementsByTagName ('link');

		for (var i = 0, il = links.length; i < il; i++) {
			if (links[i].getAttribute ('href') === themeCSS) {
				appendCSS = false;
				break;
			}
		}
	}

	// Create link element for comment stylesheet
	if (appendCSS === true) {
		var css = createElement ('link', {
			rel: 'stylesheet',
			href: themeCSS,
			type: 'text/css',
		});

		// Append comment stylesheet link element to page head
		head.appendChild (css);
	}

	// Put number of comments into "hashover-comment-count" identified HTML element
	if (totalCount !== 0) {
		ifElement ('hashover-comment-count', function (countElement) {
			countElement.textContent = totalCount;
		});

		// Create link element for comment RSS feed
		var rss = createElement ('link', {
			rel: 'alternate',
			href: httpRoot + '/api/rss.php?url=' + encodeURIComponent (URLHref),
			type: 'application/rss+xml',
			title: 'Comments'
		});

		// Append comment RSS feed link element to page head
		head.appendChild (rss);
	}

	// Initial HTML
	var initialHTML = '<span id="comments"></span>\n';
	    initialHTML += '<div id="hashover-form-section"><span class="hashover-title hashover-main-title hashover-dashed-title">\n';
	    initialHTML += '\t发表评论\n';
	    initialHTML += '</span>\n';
	    initialHTML += '<div id="hashover-message" class="hashover-title hashover-message"></div>\n';
	    initialHTML += '<form id="hashover-form" class="hashover-balloon" name="hashover-form" action="/hashover/scripts/postcomments.php" method="post">\n';
	    initialHTML += '\t<div class="hashover-inputs">\n';
	    initialHTML += '\t\t<div class="hashover-avatar-image">\n';
	    initialHTML += '\t\t\t<div style="background-image: url(\'/hashover/images/first-comment.svg\');"></div>\n';
	    initialHTML += '\t\t</div>\n';
	    initialHTML += '\t\t<div class="hashover-input-cell">\n';
	    initialHTML += '\t\t\t<div class="hashover-name-input">\n';
	    initialHTML += '\t\t\t\t<input id="hashover-main-name" class="hashover-input-info" type="text" name="name" title="昵称 (可选)" value="idken" placeholder="昵称">\n';
	    initialHTML += '\t\t\t</div>\n';
	    initialHTML += '\t\t</div>\n';
	    initialHTML += '\t\t<div class="hashover-input-cell">\n';
	    initialHTML += '\t\t\t<div class="hashover-email-input">\n';
	    initialHTML += '\t\t\t\t<input id="hashover-main-email" class="hashover-input-info" type="email" name="email" title="邮箱 (可选，用于接收通知邮件)" value="asdad@asaa.com" placeholder="邮箱">\n';
	    initialHTML += '\t\t\t</div>\n';
	    initialHTML += '\t\t</div>\n';
	    initialHTML += '\t\t<div class="hashover-input-cell">\n';
	    initialHTML += '\t\t\t<div class="hashover-website-input">\n';
	    initialHTML += '\t\t\t\t<input id="hashover-main-website" class="hashover-input-info" type="url" name="website" title="网址 (可选)" value="" placeholder="网址">\n';
	    initialHTML += '\t\t\t</div>\n';
	    initialHTML += '\t\t</div>\n';
	    initialHTML += '\t</div>\n';
	    initialHTML += '\t<div id="hashover-requiredFields">\n';
	    initialHTML += '\t\t<input type="text" name="summary" value="">\n';
	    initialHTML += '\t\t<input type="hidden" name="age" value="">\n';
	    initialHTML += '\t\t<input type="text" name="lastname" value="">\n';
	    initialHTML += '\t\t<input type="text" name="address" value="">\n';
	    initialHTML += '\t\t<input type="hidden" name="zip" value="">\n';
	    initialHTML += '\t</div>\n';
	    initialHTML += '\t<textarea id="hashover-main-comment" class="hashover-textarea hashover-main-textarea" cols="63" name="comment" rows="5" title="接受HTML: &lt;b&gt;，&lt;u&gt;，&lt;i&gt;，&lt;s&gt;，&lt;big&gt;，&lt;em&gt;，&lt;small&gt;，&lt;strong&gt;，&lt;sub&gt;，&lt;sup&gt;，&lt;pre&gt;，&lt;ul&gt;，&lt;ol&gt;，&lt;li&gt;，&lt;blockquote&gt;，&lt;code&gt;转义HTML，网址自动成为链接，[img]URL在这里[/img] 将显示外部图像。" placeholder="在这里写点什么吧..."></textarea>\n';
	    initialHTML += '\t<input type="hidden" name="url" value="http://tildehash.com/comments.html">\n';
	    initialHTML += '\t<input type="hidden" name="title" value="HashOver ~ Free and Open Source PHP Comment System">\n';
	    initialHTML += '\t<div class="hashover-form-footer">\n';
	    initialHTML += '\t\t<label for="hashover-subscribe" class="hashover-main-label" title="订阅电子邮件通知">\n';
	    initialHTML += '\t\t\t<input id="hashover-subscribe" type="checkbox" name="subscribe" checked="true">\n';
	    initialHTML += '\t\t\t有回复通知我\n';
	    initialHTML += '\t\t</label>\n';
	    initialHTML += '\t\t<span class="hashover-form-buttons">\n';
	    initialHTML += '\t\t\t<input id="hashover-post-button" class="hashover-submit hashover-post-button" type="submit" name="post" value="发表评论" title="发表评论">\n';
	    initialHTML += '\t\t</span>\n';
	    initialHTML += '\t</div>\n';
	    initialHTML += '</form></div>\n';
	    initialHTML += '<div id="hashover-comments-section"><div id="hashover-count-wrapper" class="hashover-sort-count hashover-dashed-title">\n';
	    initialHTML += '\t<span id="hashover-count">\n';
	    initialHTML += '\t\t目前有23条评论 (31条回复)\n';
	    initialHTML += '\t</span>\n';
	    initialHTML += '\t<span id="hashover-sort" class="hashover-select-wrapper">\n';
	    initialHTML += '\t\t<select id="hashover-sort-select" name="sort" size="1">\n';
	    initialHTML += '\t\t\t<option value="ascending">按顺序</option>\n';
	    initialHTML += '\t\t\t<option value="descending">按倒序</option>\n';
	    initialHTML += '\t\t\t<option value="by-date">按日期</option>\n';
	    initialHTML += '\t\t\t<option value="by-likes">按喜欢数</option>\n';
	    initialHTML += '\t\t\t<option value="by-replies">按回复数</option>\n';
	    initialHTML += '\t\t\t<option value="by-name">按评论者</option>\n';
	    initialHTML += '\t\t\t<optgroup label="&nbsp;"></optgroup>\n';
	    initialHTML += '\t\t\t<optgroup label="更多模式">\n';
	    initialHTML += '\t\t\t\t<option value="threaded-descending">按倒序</option>\n';
	    initialHTML += '\t\t\t\t<option value="threaded-by-date">按日期</option>\n';
	    initialHTML += '\t\t\t\t<option value="threaded-by-likes">按喜欢数</option>\n';
	    initialHTML += '\t\t\t\t<option value="by-popularity">按人气数</option>\n';
	    initialHTML += '\t\t\t\t<option value="by-discussion">按讨论数</option>\n';
	    initialHTML += '\t\t\t\t<option value="threaded-by-name">按评论者</option>\n';
	    initialHTML += '\t\t\t</optgroup>\n';
	    initialHTML += '\t\t</select>\n';
	    initialHTML += '\t</span>\n';
	    initialHTML += '</div>\n';
	    initialHTML += '<div id="hashover-sort-div"></div></div>\n';
	    initialHTML += '<div id="hashover-end-links">\n';
	    initialHTML += '\t<a href="http://tildehash.com/?page=hashover" id="hashover-home-link" target="_blank">HashOver条评论</a> &#8210;\n';
	    initialHTML += '\t<a href="/hashover/api/rss.php?url=http%3A%2F%2Ftildehash.com%2Fcomments.html" id="hashover-rss-link" target="_blank">RSS订阅</a> &middot;\n';
	    initialHTML += '\t<a href="/hashover/scripts/hashover.php?source" id="hashover-source-link" rel="hashover-source" target="_blank">源代码</a> &middot;\n';
	    initialHTML += '\t<a href="/hashover/scripts/hashover-javascript.php?url=http%3A%2F%2Ftildehash.com%2Fcomments.html&title=HashOver+%7E+Free+and+Open+Source+PHP+Comment+System&hashover-script=1" id="hashover-javascript-link" rel="hashover-javascript" target="_blank">JavaScript</a>\n';
	    initialHTML += '</div>\n';

	// Create div tag for HashOver comments to appear in
	if (HashOverDiv === null) {
		HashOverDiv = createElement ('div', { id: 'hashover' });

		// Place HashOver element on page
		if (hashoverScript !== false) {
			var thisScript = getElement ('hashover-script-' + hashoverScript);
			    thisScript.parentNode.insertBefore (HashOverDiv, thisScript);
		} else {
			document.body.appendChild (HashOverDiv);
		}
	}

	// Add class for differentiating desktop and mobile styling
	HashOverDiv.className = 'hashover-' + deviceType;

	// Add class to indicate user login status
	if (userIsLoggedIn === true) {
		addClass (HashOverDiv, 'hashover-logged-in');
	} else {
		addClass (HashOverDiv, 'hashover-logged-out');
	}

	// Add initial HTML to page
	if ('insertAdjacentHTML' in HashOverDiv) {
		HashOverDiv.insertAdjacentHTML ('beforeend', initialHTML);
	} else {
		HashOverDiv.innerHTML = initialHTML;
	}

	// Content passed from PHP
	var PHPContent = {
		"comments": [
			{
				"permalink": "c1",
				"name": "yufan",
				"avatar": "https:\/\/secure.gravatar.com\/avatar\/0efc20f1bb0071ed50bd729e341865b1.png?r=pg&s=55&d=https%3A%2F%2Fweibo.honya.top%2Fhashover%2Fimages%2Favatar.png",
				"website": "http:\/\/yufanboke.top",
				"subscribed": true,
				"date": "22\u5929\u524d",
				"sort-date": 1490411294,
				"body": "\u5927\u7684\u8428\u8fbe\u901f\u5ea6",
				"replies": [
					{
						"permalink": "c1r1",
						"name": "yufan",
						"avatar": "https:\/\/secure.gravatar.com\/avatar\/0efc20f1bb0071ed50bd729e341865b1.png?r=pg&s=55&d=https%3A%2F%2Fweibo.honya.top%2Fhashover%2Fimages%2Favatar.png",
						"website": "http:\/\/yufanboke.top",
						"subscribed": true,
						"date": "22\u5929\u524d",
						"sort-date": 1490411307,
						"body": "\u554a\u98d2\u98d2\u7684",
						"replies": [
							{
								"permalink": "c1r1r1",
								"name": "chenyufan",
								"avatar": "https:\/\/secure.gravatar.com\/avatar\/f597976ca64dca6cfd66b424b2428bcc.png?r=pg&s=55&d=https%3A%2F%2Fweibo.honya.top%2Fhashover%2Fimages%2Favatar.png",
								"website": "https:\/\/yufanboke.top",
								"subscribed": true,
								"date": "22\u5929\u524d",
								"sort-date": 1490456673,
								"body": "\u6d4b\u8bd5",
								"replies": [
									{
										"permalink": "c1r1r1r1",
										"name": "yufan",
										"avatar": "https:\/\/secure.gravatar.com\/avatar\/0efc20f1bb0071ed50bd729e341865b1.png?r=pg&s=55&d=https%3A%2F%2Fweibo.honya.top%2Fhashover%2Fimages%2Favatar.png",
										"website": "https:\/\/yufanboke.top",
										"subscribed": true,
										"date": "17\u5929\u524d",
										"sort-date": 1490885536,
										"body": "jjj"
									}
								]
							}
						]
					}
				]
			},
			{
				"permalink": "c2",
				"name": "yufan",
				"avatar": "https:\/\/secure.gravatar.com\/avatar\/0efc20f1bb0071ed50bd729e341865b1.png?r=pg&s=55&d=https%3A%2F%2Fweibo.honya.top%2Fhashover%2Fimages%2Favatar.png",
				"website": "http:\/\/yufanboke.top",
				"subscribed": true,
				"date": "22\u5929\u524d",
				"sort-date": 1490412141,
				"body": "\u53d1\u7684\u6492\u963f\u8428\u5fb7"
			},
			{
				"permalink": "c3",
				"name": "yufan",
				"avatar": "https:\/\/secure.gravatar.com\/avatar\/0efc20f1bb0071ed50bd729e341865b1.png?r=pg&s=55&d=https%3A%2F%2Fweibo.honya.top%2Fhashover%2Fimages%2Favatar.png",
				"website": "https:\/\/yufanboke.top",
				"subscribed": true,
				"date": "22\u5929\u524d",
				"sort-date": 1490412161,
				"body": "\u5927\u58f0\u9053\u554a"
			},
			{
				"permalink": "c4",
				"name": "yufan",
				"avatar": "https:\/\/secure.gravatar.com\/avatar\/0efc20f1bb0071ed50bd729e341865b1.png?r=pg&s=55&d=https%3A%2F%2Fweibo.honya.top%2Fhashover%2Fimages%2Favatar.png",
				"website": "https:\/\/yufanboke.top",
				"subscribed": true,
				"date": "22\u5929\u524d",
				"sort-date": 1490415433,
				"body": "\u6d4b\u662f"
			},
			{
				"permalink": "c5",
				"name": "chenyufan",
				"avatar": "https:\/\/secure.gravatar.com\/avatar\/f597976ca64dca6cfd66b424b2428bcc.png?r=pg&s=55&d=https%3A%2F%2Fweibo.honya.top%2Fhashover%2Fimages%2Favatar.png",
				"website": "https:\/\/yufanboke.top",
				"subscribed": true,
				"date": "22\u5929\u524d",
				"sort-date": 1490455494,
				"body": "\u6d4b\u8bd5\u4e0b"
			},
			{
				"permalink": "c6",
				"name": "chenyufan",
				"avatar": "https:\/\/secure.gravatar.com\/avatar\/f597976ca64dca6cfd66b424b2428bcc.png?r=pg&s=55&d=https%3A%2F%2Fweibo.honya.top%2Fhashover%2Fimages%2Favatar.png",
				"website": "https:\/\/yufanboke.top",
				"subscribed": true,
				"date": "22\u5929\u524d",
				"sort-date": 1490455507,
				"body": "\u6d4b\u8bd5"
			},
			{
				"permalink": "c7",
				"name": "chenyufan",
				"avatar": "https:\/\/secure.gravatar.com\/avatar\/f597976ca64dca6cfd66b424b2428bcc.png?r=pg&s=55&d=https%3A%2F%2Fweibo.honya.top%2Fhashover%2Fimages%2Favatar.png",
				"website": "https:\/\/yufanboke.top",
				"subscribed": true,
				"date": "22\u5929\u524d",
				"sort-date": 1490455531,
				"body": "\u54c8\u54c8GV"
			},
			{
				"permalink": "c8",
				"name": "chenyufan",
				"avatar": "https:\/\/secure.gravatar.com\/avatar\/f597976ca64dca6cfd66b424b2428bcc.png?r=pg&s=55&d=https%3A%2F%2Fweibo.honya.top%2Fhashover%2Fimages%2Favatar.png",
				"website": "https:\/\/yufanboke.top",
				"subscribed": true,
				"date": "22\u5929\u524d",
				"sort-date": 1490455553,
				"body": "\u597d\u5927\u7684"
			},
			{
				"permalink": "c9",
				"name": "chenyufan",
				"avatar": "https:\/\/secure.gravatar.com\/avatar\/f597976ca64dca6cfd66b424b2428bcc.png?r=pg&s=55&d=https%3A%2F%2Fweibo.honya.top%2Fhashover%2Fimages%2Favatar.png",
				"website": "https:\/\/yufanboke.top",
				"subscribed": true,
				"date": "22\u5929\u524d",
				"sort-date": 1490455565,
				"body": "\u5e7f\u544a\u8d39"
			},
			{
				"permalink": "c10",
				"name": "chenyufan",
				"avatar": "https:\/\/secure.gravatar.com\/avatar\/f597976ca64dca6cfd66b424b2428bcc.png?r=pg&s=55&d=https%3A%2F%2Fweibo.honya.top%2Fhashover%2Fimages%2Favatar.png",
				"website": "https:\/\/yufanboke.top",
				"subscribed": true,
				"date": "22\u5929\u524d",
				"sort-date": 1490455594,
				"body": "\u89c4\u5f8b"
			},
			{
				"permalink": "c11",
				"name": "chenyufan",
				"avatar": "https:\/\/secure.gravatar.com\/avatar\/f597976ca64dca6cfd66b424b2428bcc.png?r=pg&s=55&d=https%3A%2F%2Fweibo.honya.top%2Fhashover%2Fimages%2Favatar.png",
				"website": "https:\/\/yufanboke.top",
				"subscribed": true,
				"date": "22\u5929\u524d",
				"sort-date": 1490455620,
				"body": "\u6d4b\u8bd5"
			},
			{
				"permalink": "c12",
				"name": "chenyufan",
				"avatar": "https:\/\/secure.gravatar.com\/avatar\/f597976ca64dca6cfd66b424b2428bcc.png?r=pg&s=55&d=https%3A%2F%2Fweibo.honya.top%2Fhashover%2Fimages%2Favatar.png",
				"website": "https:\/\/yufanboke.top",
				"subscribed": true,
				"date": "22\u5929\u524d",
				"sort-date": 1490455626,
				"body": "\u6d4b\u8bd5"
			},
			{
				"permalink": "c13",
				"name": "chenyufan",
				"avatar": "https:\/\/secure.gravatar.com\/avatar\/f597976ca64dca6cfd66b424b2428bcc.png?r=pg&s=55&d=https%3A%2F%2Fweibo.honya.top%2Fhashover%2Fimages%2Favatar.png",
				"website": "https:\/\/yufanboke.top",
				"subscribed": true,
				"date": "22\u5929\u524d",
				"sort-date": 1490455994,
				"body": "ce"
			},
			{
				"permalink": "c14",
				"name": "chenyufan",
				"avatar": "https:\/\/secure.gravatar.com\/avatar\/f597976ca64dca6cfd66b424b2428bcc.png?r=pg&s=55&d=https%3A%2F%2Fweibo.honya.top%2Fhashover%2Fimages%2Favatar.png",
				"website": "https:\/\/yufanboke.top",
				"subscribed": true,
				"date": "21\u5929\u524d",
				"sort-date": 1490503174,
				"body": "\u6d4b\u8bd5",
				"replies": [
					{
						"permalink": "c14r1",
						"name": "yufan",
						"avatar": "https:\/\/secure.gravatar.com\/avatar\/0efc20f1bb0071ed50bd729e341865b1.png?r=pg&s=55&d=https%3A%2F%2Fweibo.honya.top%2Fhashover%2Fimages%2Favatar.png",
						"website": "https:\/\/yufanboke.top",
						"subscribed": true,
						"date": "16\u5929\u524d",
						"sort-date": 1490963012,
						"body": "\u6d4b\u8bd5"
					},
					{
						"permalink": "c14r2",
						"name": "yufan",
						"avatar": "https:\/\/secure.gravatar.com\/avatar\/0efc20f1bb0071ed50bd729e341865b1.png?r=pg&s=55&d=https%3A%2F%2Fweibo.honya.top%2Fhashover%2Fimages%2Favatar.png",
						"website": "https:\/\/yufanboke.top",
						"subscribed": true,
						"date": "16\u5929\u524d",
						"sort-date": 1490967681,
						"body": "\u6d4b\u8bd5"
					},
					{
						"permalink": "c14r3",
						"name": "yufan",
						"avatar": "https:\/\/secure.gravatar.com\/avatar\/0efc20f1bb0071ed50bd729e341865b1.png?r=pg&s=55&d=https%3A%2F%2Fweibo.honya.top%2Fhashover%2Fimages%2Favatar.png",
						"website": "https:\/\/yufanboke.top",
						"subscribed": true,
						"date": "14\u5929\u524d",
						"sort-date": 1491117689,
						"body": "\u6d4b\u8bd5"
					}
				]
			},
			{
				"permalink": "c15",
				"name": "yufan",
				"avatar": "https:\/\/secure.gravatar.com\/avatar\/0efc20f1bb0071ed50bd729e341865b1.png?r=pg&s=55&d=https%3A%2F%2Fweibo.honya.top%2Fhashover%2Fimages%2Favatar.png",
				"website": "https:\/\/yufanboke.top",
				"subscribed": true,
				"date": "21\u5929\u524d",
				"sort-date": 1490538220,
				"body": "ceshi"
			},
			{
				"permalink": "c16",
				"name": "yufan",
				"avatar": "https:\/\/secure.gravatar.com\/avatar\/0efc20f1bb0071ed50bd729e341865b1.png?r=pg&s=55&d=https%3A%2F%2Fweibo.honya.top%2Fhashover%2Fimages%2Favatar.png",
				"website": "https:\/\/m.baidu.com\/?from=1015294a",
				"subscribed": true,
				"date": "16\u5929\u524d",
				"sort-date": 1490934912,
				"body": "\u6ef4\u6ef4"
			},
			{
				"permalink": "c17",
				"name": "yufan",
				"avatar": "https:\/\/secure.gravatar.com\/avatar\/0efc20f1bb0071ed50bd729e341865b1.png?r=pg&s=55&d=https%3A%2F%2Fweibo.honya.top%2Fhashover%2Fimages%2Favatar.png",
				"website": "https:\/\/yufanboke.top",
				"subscribed": true,
				"date": "16\u5929\u524d",
				"sort-date": 1490971065,
				"body": "\u6d4b\u8bd5"
			},
			{
				"permalink": "c18",
				"name": "yufan",
				"avatar": "https:\/\/secure.gravatar.com\/avatar\/0efc20f1bb0071ed50bd729e341865b1.png?r=pg&s=55&d=https%3A%2F%2Fweibo.honya.top%2Fhashover%2Fimages%2Favatar.png",
				"website": "https:\/\/yufanboke.top",
				"subscribed": true,
				"date": "16\u5929\u524d",
				"sort-date": 1490971134,
				"body": "\u6d4b\u8bd5"
			},
			{
				"permalink": "c19",
				"name": "yufan",
				"avatar": "https:\/\/secure.gravatar.com\/avatar\/0efc20f1bb0071ed50bd729e341865b1.png?r=pg&s=55&d=https%3A%2F%2Fweibo.honya.top%2Fhashover%2Fimages%2Favatar.png",
				"website": "https:\/\/yufanboke.top",
				"subscribed": true,
				"date": "16\u5929\u524d",
				"sort-date": 1490971177,
				"body": "\u6d4b\u8bd5"
			},
			{
				"permalink": "c20",
				"name": "yufan",
				"avatar": "https:\/\/secure.gravatar.com\/avatar\/0efc20f1bb0071ed50bd729e341865b1.png?r=pg&s=55&d=https%3A%2F%2Fweibo.honya.top%2Fhashover%2Fimages%2Favatar.png",
				"website": "https:\/\/yufanboke.top",
				"subscribed": true,
				"date": "16\u5929\u524d",
				"sort-date": 1490971187,
				"body": "\u6d4b\u8bd5"
			},
			{
				"permalink": "c21",
				"name": "yufan",
				"avatar": "https:\/\/secure.gravatar.com\/avatar\/f597976ca64dca6cfd66b424b2428bcc.png?r=pg&s=55&d=https%3A%2F%2Fweibo.honya.top%2Fhashover%2Fimages%2Favatar.png",
				"website": "https:\/\/yufanboke.top",
				"subscribed": true,
				"date": "14\u5929\u524d",
				"sort-date": 1491117785,
				"body": "\u6d4b\u8bd5",
				"replies": [
					{
						"permalink": "c21r1",
						"name": "yufan",
						"avatar": "https:\/\/secure.gravatar.com\/avatar\/0efc20f1bb0071ed50bd729e341865b1.png?r=pg&s=55&d=https%3A%2F%2Fweibo.honya.top%2Fhashover%2Fimages%2Favatar.png",
						"website": "https:\/\/yufanboke.top",
						"subscribed": true,
						"date": "14\u5929\u524d",
						"sort-date": 1491117819,
						"body": "\u6d4b\u8bd5"
					},
					{
						"permalink": "c21r2",
						"name": "yufan",
						"avatar": "https:\/\/secure.gravatar.com\/avatar\/0efc20f1bb0071ed50bd729e341865b1.png?r=pg&s=55&d=https%3A%2F%2Fweibo.honya.top%2Fhashover%2Fimages%2Favatar.png",
						"website": "https:\/\/yufanboke.top",
						"subscribed": true,
						"date": "14\u5929\u524d",
						"sort-date": 1491117855,
						"body": "\u6d4b\u8bd5\u56de\u590d\u901a\u77e5"
					}
				]
			},
			{
				"permalink": "c22",
				"name": "yufan",
				"avatar": "https:\/\/secure.gravatar.com\/avatar\/0efc20f1bb0071ed50bd729e341865b1.png?r=pg&s=55&d=https%3A%2F%2Fweibo.honya.top%2Fhashover%2Fimages%2Favatar.png",
				"website": "https:\/\/yufanboke.top",
				"subscribed": true,
				"date": "14\u5929\u524d",
				"sort-date": 1491117798,
				"body": "\u6d4b\u8bd5"
			},
			{
				"permalink": "c23",
				"name": "yufan",
				"avatar": "https:\/\/secure.gravatar.com\/avatar\/0efc20f1bb0071ed50bd729e341865b1.png?r=pg&s=55&d=https%3A%2F%2Fweibo.honya.top%2Fhashover%2Fimages%2Favatar.png",
				"website": "https:\/\/yufanboke.top",
				"subscribed": true,
				"date": "5\u5929\u524d",
				"sort-date": 1491886515,
				"body": "test"
			}
		],
		"popularComments": []
	};

	// Get sort div element
	sortDiv = getElement ('hashover-sort-div');

	// Get primary form element
	HashOverForm = getElement ('hashover-form');

	// Display most popular comments
	ifElement ('hashover-top-comments', function (topComments) {
		if (PHPContent.popularComments[0] !== undefined) {
			parseAll (PHPContent.popularComments, topComments, false, true);
		}
	});

	// Add initial event handlers
	parseAll (PHPContent.comments, sortDiv, collapseComments);

	// Attach event listeners to "Post Comment" button
	var postButton = getElement ('hashover-post-button');

	// Onclick
	postButton.onclick = function ()
	{
		return postComment (sortDiv, HashOverForm, postButton, AJAXPost);
	};

	// Onsubmit
	postButton.onsubmit = function ()
	{
		return postComment (sortDiv, HashOverForm, postButton, AJAXPost);
	};

	// Five method sort
	ifElement ('hashover-sort-select', function (sortSelect) {
		sortSelect.onchange = function ()
		{
			sortDiv.textContent = '';
			sortComments (sortSelect.value);
		};
	});

	// Display reply or edit form when the proper URL queries are set
	if (URLHref.match (/hashover-(reply|edit)=/)) {
		var permalink = URLHref.replace (/.*?hashover-(edit|reply)=(c[0-9r\-pop]+).*?/, '$2');

		if (!URLHref.match ('hashover-edit=')) {
			// Display reply form
			hashoverReply (permalink);
		} else {
			var isPop = permalink.match ('-pop');
			var comments = (isPop) ? PHPContent.popularComments : PHPContent.comments;
			// Display edit form
			hashoverEdit (findByPermalink (permalink, comments));
		}
	}

	// Log execution time in JavaScript console
	if (window.console) {
		console.log ('HashOver executed in ' + (Date.now () - execStart) + ' ms.');
	}

	// Callback for scrolling a comment into view on page load
	var scroller = function ()
	{
		setTimeout (function () {
			// Workaround for stupid Chrome bug
			if (URLHash.match (/comments|hashover/)) {
				ifElement (URLHash, function (comments) {
					comments.scrollIntoView ({ behavior: 'smooth' });
				});
			}

			// Jump to linked comment
			if (URLHash.match (/c[0-9]+r*/)) {
				ifElement (URLHash, function (comment) {
					comment.scrollIntoView ({ behavior: 'smooth' });
				});
			}
		}, 500);
	};

	// Page onload compatibility wrapper
	if (window.addEventListener) {
		// Rest of the world
		window.addEventListener ('load', scroller, false);
	} else {
		// IE ~8
		window.attachEvent ('onload', scroller);
	}

	// Open the message element if there's a message
	if (getElement ('hashover-message').textContent !== '') {
		showMessage ();
	}
};

// Initiate HashOver
HashOver.init ();

/*

	HashOver Statistics:

		Execution Time     : 39.75606 ms
		Script Memory Peak : 1.64 MiB
		System Memory Peak : 2 MiB

*/