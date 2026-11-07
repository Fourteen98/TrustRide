import { createContext, useContext } from 'react';

/**
 * How much of the bottom of the screen the floating tab bar covers. Screens inside the tabs keep their
 * last lines clear of it. Zero outside the tabs.
 */
export const TabBarSpace = createContext(0);

export function useTabBarSpace(): number {
  return useContext(TabBarSpace);
}
