module.exports = ({ marp }) => marp
    .use(require('markdown-it-container'), 'fragment', {
        render(tokens, idx) {
            const token = tokens[idx];

            const info = token.info.trim();
            const classMatch = info.match(/class="(.+?)"/);
            const className = classMatch ? classMatch[1] : '';

            if (token.nesting === 1) {
                return `<div data-marpit-fragment class="${className}">`;
            } else {
                return '</div>';
            }
        }
    });
