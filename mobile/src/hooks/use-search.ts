import { useEffect, useRef, useState } from 'react';

import { messageOf } from '@/lib/errors';

type Answer<T> = { query: string; data: T | null; error: string | null };

/**
 * Search as she types: asks once she pauses for `waitMs`, and only for text of at least two letters.
 * Only the answer to what is in the box now is shown, so a slow answer to older text never replaces it.
 */
export function useSearch<T>(text: string, search: (text: string) => Promise<T>, waitMs = 300) {
  const [answer, setAnswer] = useState<Answer<T>>({ query: '', data: null, error: null });
  const searchRef = useRef(search);
  const query = text.trim();

  useEffect(() => {
    searchRef.current = search;
  });

  useEffect(() => {
    if (query.length < 2) {
      return;
    }

    let current = true;
    const timer = setTimeout(() => {
      searchRef
        .current(query)
        .then((data) => current && setAnswer({ query, data, error: null }))
        .catch((e) => current && setAnswer({ query, data: null, error: messageOf(e) }));
    }, waitMs);

    return () => {
      current = false;
      clearTimeout(timer);
    };
  }, [query, waitMs]);

  const fresh = query.length >= 2 && answer.query === query;

  return { data: fresh ? answer.data : null, error: fresh ? answer.error : null, loading: query.length >= 2 && !fresh };
}
