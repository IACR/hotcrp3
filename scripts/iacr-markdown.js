// iacr/iacr-markdown.js -- render HotCRP's text format 1 as Markdown, with
// mathematics between $...$ or $$...$$ typeset by KaTeX. Reviews and comments
// in format 1, such as the deep reviews AICheck saves, then display as
// formatted text rather than raw Markdown.
//
// HotCRP has no Markdown renderer of its own: a format is a renderer added
// with hotcrp.render_text.add_format, and the instance names its formats in
// $Opt["formatInfo"]. A review is shown in the instance's default format
// ($Opt["defaultFormat"]), so turning this on applies to every review and
// comment, including authors' responses. Text that matches simple_regex (no
// Markdown characters) stays in plain format 0.
//
// Install: copy this file into the HotCRP checkout's scripts/iacr/ directory
// (which holds IACR's additions, apart from upstream's files), with the
// libraries it uses, which are kept in js/ beside it (downloaded from
// jsdelivr: markdown-it 14.1.0, KaTeX 0.16.11, markdown-it-texmath 1.0.0 and
// DOMPurify 3.2.4). KaTeX's stylesheet finds its fonts in fonts/ beside it, so
// js/katex is copied whole. From this directory, with HOTCRP the checkout:
//
//   mkdir -p $HOTCRP/scripts/iacr
//   cp -r iacr-markdown.js js/* $HOTCRP/scripts/iacr/
//
// They are served from the instance rather than a CDN, so a compromised CDN
// cannot put script into HotCRP's pages. Then, in conf/options.php (this file
// must come after the libraries):
//
//   $Opt["defaultFormat"] = 1;
//   $Opt["formatInfo"] = [1 => [
//       "name" => "Markdown",
//       "description" => "Markdown, with LaTeX maths between \$...\$",
//       "has_preview" => true,
//       "simple_regex" => '/\A[^\[\]\$*_`#<>|~\\\\]*\z/'
//   ]];
//   $Opt["scripts"] = [
//       "scripts/iacr.js",
//       "scripts/iacr/markdown-it.min.js",
//       "scripts/iacr/katex.min.js",
//       "scripts/iacr/texmath.js",
//       "scripts/iacr/purify.min.js",
//       "scripts/iacr/iacr-markdown.js"
//   ];
//   $Opt["stylesheets"] = [
//       "stylesheets/iacr.css",
//       "scripts/iacr/katex/katex.min.css"
//   ];
//
// The text may be written by an adversarial reviewer or author, or come from a
// submission through an LLM's review, so it is treated as untrusted:
//
// * markdown-it escapes raw HTML (html: false) and refuses javascript:, data:
//   and similar links. Images are turned off, so a text cannot make a reader's
//   browser fetch a URL (a beacon telling its writer who read it, and when).
// * KaTeX runs with trust off, so \href, \url, \includegraphics, \htmlStyle and
//   the like render as errors. Sizes are capped at MAX_SIZE ems and macro
//   expansion at MAX_EXPAND, so a formula cannot draw a huge box over the page
//   or freeze the browser.
// * The HTML is then passed through DOMPurify, so a bug in any of these
//   libraries still cannot put script, event handlers or links of unsafe
//   schemes into the page. Without DOMPurify, format 1 is not registered and
//   HotCRP shows such text as plain text.
// * Each formula is clipped to its own box, and each text to its element
//   (CLIP_CSS, which also keeps the text to HotCRP's width), so negative kerns (which maxSize does not cap) or \mathllap
//   cannot place text over other parts of the page.

(function () {
"use strict";

// The largest size, in ems, a formula may give anything (\rule, \kern, ...).
var MAX_SIZE = 5;
// How many macro expansions a formula may make (KaTeX's default is 1000).
var MAX_EXPAND = 200;
// Layout for text in format 1, added to the page once, by add_clip_css:
// * HotCRP's review card is a grid whose columns grow to fit the widest thing
//   in them that cannot wrap, and HotCRP limits only its own plain format, to
//   720px (.format0 in style.css). Text in format 1 is limited to MAX_WIDTH,
//   wider than plain text, as maths reads better on longer lines; a longer
//   code line or formula scrolls inside it instead of widening the page.
// * Code blocks wrap their lines, as plain text does.
// * Each formula is clipped to its box, with a little room for glyphs that
//   overhang it, and each text to its element, which scrolls if needed.
var MAX_WIDTH = "900px";
var CLIP_CSS = ".format1:not(span) { overflow: auto; }\n"
    + ".revtext.format1, .cmttext.format1 { max-width: " + MAX_WIDTH + "; }\n"
    + ".format1 pre { white-space: pre-wrap; overflow-wrap: anywhere; }\n"
    + ".format1 .katex { clip-path: inset(-0.3em); }\n"
    + ".format1 .katex-display { overflow-x: auto; overflow-y: hidden; }";
// Elements texmath wraps formulas in, which DOMPurify does not know.
var EXTRA_TAGS = ["eq", "eqn"];

var parser = null;

function markdown() {
    if (parser === null) {
        parser = window.markdownit({html: false, linkify: true, typographer: false});
        parser.disable("image");
        if (window.texmath && window.katex) {
            parser.use(window.texmath, {
                engine: window.katex,
                delimiters: "dollars",
                katexOptions: {throwOnError: false, trust: false, strict: "ignore",
                               maxSize: MAX_SIZE, maxExpand: MAX_EXPAND}
            });
        }
    }
    return parser;
}

function clean(html) {
    return window.DOMPurify.sanitize(html, {ADD_TAGS: EXTRA_TAGS, FORBID_TAGS: ["img", "style"]});
}

function add_clip_css() {
    var style = document.createElement("style");
    style.textContent = CLIP_CSS;
    document.head.appendChild(style);
}

if (window.hotcrp && hotcrp.render_text && window.markdownit
    && window.DOMPurify && window.DOMPurify.isSupported) {
    add_clip_css();
    hotcrp.render_text.add_format({
        format: 1,
        render: function (text) {
            return clean(markdown().render(text));
        },
        render_inline: function (text) {
            return clean(markdown().renderInline(text));
        }
    });
}
})();
