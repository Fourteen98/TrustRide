/** The web build's version of WebPage: a plain iframe. */
export function WebPage({ url, title }: { url: string; title: string }) {
  return <iframe src={url} title={title} style={{ flex: 1, border: 0, width: '100%', height: '100%' }} />;
}
