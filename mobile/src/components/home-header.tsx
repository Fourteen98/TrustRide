import { Link } from 'expo-router';
import { Pressable, StyleSheet, View } from 'react-native';

import { screenRoles, useAuth } from '@/auth/auth-context';
import { Spacing } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';

import { ThemedText } from './themed-text';

function HeaderLink({ href, label }: { href: '/help' | '/account'; label: string }) {
  const theme = useTheme();

  return (
    <Link href={href} asChild>
      <Pressable accessibilityRole="button" hitSlop={12}>
        <ThemedText type="smallBold" style={{ color: theme.primaryText }}>
          {label}
        </ThemedText>
      </Pressable>
    </Link>
  );
}

/** The top of home: who is logged in, the ways to help and to the account screen, and the role switcher if they hold more than one role. */
export function HomeHeader() {
  const { account, activeRole, can } = useAuth();

  return (
    <>
      <View style={styles.header}>
        <View style={{ flex: 1 }}>
          <ThemedText type="small" themeColor="textSecondary">
            {activeRole?.label ?? 'Aveo'}
          </ThemedText>
          <ThemedText type="subtitle">{account?.name ?? 'Welcome'}</ThemedText>
        </View>
        {can('support.alert.raise') ? <HeaderLink href="/help" label="Help" /> : null}
        <HeaderLink href="/account" label="Account" />
      </View>

      <RoleSwitcher />
    </>
  );
}

/** Chips to switch between roles, for an account that holds more than one. Nothing for everyone else. */
export function RoleSwitcher() {
  const theme = useTheme();
  const { account, activeRole, switchRole } = useAuth();
  const roles = screenRoles(account);

  if (roles.length < 2) {
    return null;
  }

  return (
    <View style={styles.switcher} accessibilityRole="tablist">
      {roles.map((role) => {
        const selected = role.name === activeRole?.name;

        return (
          <Pressable
            key={role.name}
            accessibilityRole="tab"
            accessibilityState={{ selected }}
            onPress={() => switchRole(role.name)}
            style={[
              styles.chip,
              { borderColor: theme.border },
              selected && { backgroundColor: theme.primary, borderColor: theme.primary },
            ]}>
            <ThemedText type="smallBold" style={selected ? { color: theme.onPrimary } : undefined}>
              {role.label ?? role.name}
            </ThemedText>
          </Pressable>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  header: { flexDirection: 'row', alignItems: 'flex-start', gap: Spacing.three },
  switcher: { flexDirection: 'row', flexWrap: 'wrap', gap: Spacing.two },
  chip: { paddingVertical: Spacing.two, paddingHorizontal: Spacing.three, borderRadius: 999, borderWidth: 1 },
});
