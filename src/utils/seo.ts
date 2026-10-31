interface SeoMetaInput {
  title: string;
  description: string;
  canonicalPath: string;
  ogImage?: string;
}

export function actualizarMetaSeo({
  title,
  description,
  canonicalPath,
  ogImage,
}: SeoMetaInput): void {
  document.title = title;

  const setMeta = (selector: string, attr: 'name' | 'property', key: string, content: string) => {
    let el = document.querySelector<HTMLMetaElement>(selector);
    if (!el) {
      el = document.createElement('meta');
      el.setAttribute(attr, key);
      document.head.appendChild(el);
    }
    el.setAttribute('content', content);
  };

  setMeta('meta[name="description"]', 'name', 'description', description);
  setMeta('meta[property="og:title"]', 'property', 'og:title', title);
  setMeta('meta[property="og:description"]', 'property', 'og:description', description);

  const canonicalUrl = `https://dipudata.com${canonicalPath}`;
  setMeta('meta[property="og:url"]', 'property', 'og:url', canonicalUrl);

  let linkCanonical = document.querySelector<HTMLLinkElement>('link[rel="canonical"]');
  if (!linkCanonical) {
    linkCanonical = document.createElement('link');
    linkCanonical.setAttribute('rel', 'canonical');
    document.head.appendChild(linkCanonical);
  }
  linkCanonical.setAttribute('href', canonicalUrl);

  if (ogImage) {
    setMeta('meta[property="og:image"]', 'property', 'og:image', ogImage);
  }
}
