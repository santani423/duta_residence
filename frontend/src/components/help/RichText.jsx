import { Fragment } from 'react';

// Parser markdown-ringan tanpa dependency baru: paragraf (baris kosong ganda),
// baris baru tunggal, **bold**, [teks](url), dan daftar "- item". Dirender sebagai
// node React langsung (bukan dangerouslySetInnerHTML) supaya aman dari injeksi HTML.
function renderInline(text, keyPrefix) {
  const pattern = /\*\*(.+?)\*\*|\[(.+?)\]\((.+?)\)/g;
  const parts = [];
  let lastIndex = 0;
  let match;
  let index = 0;

  while ((match = pattern.exec(text))) {
    if (match.index > lastIndex) parts.push(text.slice(lastIndex, match.index));
    if (match[1] !== undefined) {
      parts.push(<strong key={`${keyPrefix}-${index++}`}>{match[1]}</strong>);
    } else {
      parts.push(<a key={`${keyPrefix}-${index++}`} href={match[3]} target="_blank" rel="noreferrer">{match[2]}</a>);
    }
    lastIndex = pattern.lastIndex;
  }
  if (lastIndex < text.length) parts.push(text.slice(lastIndex));

  return parts;
}

export default function RichText({ text }) {
  if (!text) return null;
  const blocks = text.split(/\n{2,}/);

  return (
    <>
      {blocks.map((block, blockIndex) => {
        const lines = block.split('\n').filter((line) => line.trim() !== '');
        const isList = lines.length > 0 && lines.every((line) => line.trim().startsWith('- '));

        if (isList) {
          return (
            <ul key={blockIndex} style={{ marginBottom: 12, paddingLeft: 20 }}>
              {lines.map((line, lineIndex) => (
                <li key={lineIndex}>{renderInline(line.trim().slice(2), `${blockIndex}-${lineIndex}`)}</li>
              ))}
            </ul>
          );
        }

        return (
          <p key={blockIndex} style={{ marginBottom: 12 }}>
            {lines.map((line, lineIndex) => (
              <Fragment key={lineIndex}>
                {lineIndex > 0 ? <br /> : null}
                {renderInline(line, `${blockIndex}-${lineIndex}`)}
              </Fragment>
            ))}
          </p>
        );
      })}
    </>
  );
}
