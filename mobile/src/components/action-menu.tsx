import type { SFSymbol } from 'expo-symbols';
import { Modal, Pressable, StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { MaxContentWidth, Spacing } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';

import { Icon } from './icon';
import { MutedText } from './muted-text';
import { ThemedText } from './themed-text';

export type MenuItem = {
  label: string;
  /** A short line under the label: what will happen. */
  detail?: string;
  icon: SFSymbol;
  /** Shown where the symbol can't be: Android and the web. */
  glyph: string;
  /** "danger" for something that gives up her seat for the day. */
  tone?: 'plain' | 'danger';
  onPress: () => void;
};

/**
 * A menu that floats up from the bottom over a dimmed screen, for the few things that can be done to one thing,
 * such as today's ride. Tapping outside or "Close" shuts it. Choosing an item shuts it first, then does the item.
 */
export function ActionMenu({ title, items, open, onClose }: { title: string; items: MenuItem[]; open: boolean; onClose: () => void }) {
  const theme = useTheme();
  const insets = useSafeAreaInsets();

  return (
    <Modal visible={open} transparent animationType="fade" onRequestClose={onClose} statusBarTranslucent>
      <Pressable accessibilityLabel="Close the menu" style={styles.backdrop} onPress={onClose} />
      <View pointerEvents="box-none" style={[styles.wrap, { paddingBottom: insets.bottom + Spacing.three }]}>
        <View style={[styles.card, { backgroundColor: theme.background }]}>
          <ThemedText type="smallBold" themeColor="textSecondary" style={styles.title}>
            {title}
          </ThemedText>
          {items.map((item) => {
            const color = item.tone === 'danger' ? theme.danger : theme.text;

            return (
              <Pressable
                key={item.label}
                accessibilityRole="button"
                accessibilityLabel={item.label}
                accessibilityHint={item.detail}
                onPress={() => {
                  onClose();
                  item.onPress();
                }}
                style={({ pressed }) => [styles.item, pressed && { backgroundColor: theme.backgroundElement }]}>
                <View style={[styles.icon, { backgroundColor: theme.backgroundElement }]}>
                  <Icon name={item.icon} glyph={item.glyph} color={color} size={20} />
                </View>
                <View style={styles.text}>
                  <ThemedText type="smallBold" style={{ color }}>
                    {item.label}
                  </ThemedText>
                  {item.detail ? <MutedText>{item.detail}</MutedText> : null}
                </View>
              </Pressable>
            );
          })}
          <Pressable accessibilityRole="button" onPress={onClose} style={[styles.close, { backgroundColor: theme.backgroundElement }]}>
            <ThemedText type="smallBold">Close</ThemedText>
          </Pressable>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  backdrop: { position: 'absolute', top: 0, right: 0, bottom: 0, left: 0, backgroundColor: 'rgba(0,0,0,0.35)' },
  wrap: { flex: 1, justifyContent: 'flex-end', paddingHorizontal: Spacing.three },
  card: {
    width: '100%',
    maxWidth: MaxContentWidth,
    alignSelf: 'center',
    borderRadius: 24,
    padding: Spacing.two,
    gap: Spacing.half,
    shadowColor: '#000',
    shadowOpacity: 0.2,
    shadowRadius: 20,
    shadowOffset: { width: 0, height: 6 },
    elevation: 20,
  },
  title: { paddingHorizontal: Spacing.three, paddingTop: Spacing.two, paddingBottom: Spacing.one },
  item: { flexDirection: 'row', alignItems: 'center', gap: Spacing.three, padding: Spacing.two, borderRadius: 16 },
  icon: { width: 40, height: 40, borderRadius: 20, alignItems: 'center', justifyContent: 'center' },
  text: { flex: 1 },
  close: { marginTop: Spacing.one, height: 48, borderRadius: 24, alignItems: 'center', justifyContent: 'center' },
});
