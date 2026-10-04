import { StyleSheet, View } from 'react-native';

import { Spacing } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';

import { ThemedText } from './themed-text';

type Props = { body: string; caption: string; mine: boolean };

/** One line of a conversation. Hers sit on the right, the other side's on the left. */
export function MessageBubble({ body, caption, mine }: Props) {
  const theme = useTheme();

  return (
    <View style={[styles.wrap, mine ? styles.mine : styles.theirs]}>
      <View style={[styles.bubble, { backgroundColor: mine ? theme.backgroundSelected : theme.backgroundElement }]}>
        <ThemedText type="default">{body}</ThemedText>
      </View>
      <ThemedText type="small" themeColor="textSecondary">
        {caption}
      </ThemedText>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { maxWidth: '88%', gap: Spacing.one },
  mine: { alignSelf: 'flex-end', alignItems: 'flex-end' },
  theirs: { alignSelf: 'flex-start', alignItems: 'flex-start' },
  bubble: { borderRadius: 16, paddingVertical: Spacing.two, paddingHorizontal: Spacing.three },
});
