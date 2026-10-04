import { useRouter } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator } from 'react-native';

import { Button } from '@/components/button';
import { Card } from '@/components/card';
import { Choice } from '@/components/choice';
import { ChoiceGroup } from '@/components/choice-group';
import { Loadable } from '@/components/loadable';
import { MutedText } from '@/components/muted-text';
import { Notice } from '@/components/notice';
import { Screen } from '@/components/screen';
import { ThemedText } from '@/components/themed-text';
import { useAction } from '@/hooks/use-action';
import { useLoader } from '@/hooks/use-loader';
import { useTheme } from '@/hooks/use-theme';
import { longDate, moment, shortDate } from '@/lib/dates';
import { riderApi, type NoticePreview } from '@/rider/api';

/**
 * She says she won't ride on a day. Before she confirms, the app asks the server what will happen,
 * so she knows whether the day is added back. The server decides. The app only shows it.
 */
export default function NotRidingScreen() {
  const router = useRouter();
  const theme = useTheme();
  const home = useLoader(riderApi.home);
  const check = useAction();
  const confirm = useAction();

  const [date, setDate] = useState<string | null>(null);
  const [preview, setPreview] = useState<NoticePreview | null>(null);

  function choose(day: string) {
    setDate(day);
    setPreview(null);
    confirm.clear();

    return check.run(async () => setPreview(await riderApi.previewNotice(day)));
  }

  return (
    <Screen edges={['bottom']}>
      <Loadable state={home}>
        {({ ride_dates: days }) => (
          <>
            <ThemedText themeColor="textSecondary">Which day won't you ride? We'll tell the driver not to wait for you.</ThemedText>

            {days.length === 0 ? (
              <Notice tone="info" text="There are no days to choose in the next two weeks." />
            ) : (
              <ChoiceGroup title="Day" layout="chips">
                {days.map((day) => (
                  <Choice key={day} shape="chip" label={shortDate(day)} selected={date === day} onPress={() => choose(day)} />
                ))}
              </ChoiceGroup>
            )}
          </>
        )}
      </Loadable>

      {check.busy ? <ActivityIndicator color={theme.primary} /> : null}

      {preview && date ? (
        <Card title={preview.credited ? 'This day will be added back' : 'This day will not be added back'}>
          <MutedText>
            {preview.credited
              ? `Your subscription will end one day later, to make up for ${longDate(date)}.`
              : preview.in_time
                ? `Your plan's rule: ${preview.rule.toLowerCase()}.`
                : `To get a day back you need to tell us by ${moment(preview.deadline)}. That has passed, but telling us still helps the driver.`}
          </MutedText>
          <MutedText>You can't undo this in the app.</MutedText>
          <Button
            label={`I won't ride on ${shortDate(date)}`}
            loading={confirm.busy}
            onPress={() => confirm.run(() => riderApi.giveNotice(date), { then: router.back })}
          />
        </Card>
      ) : null}

      <Notice text={check.error ?? confirm.error} />
    </Screen>
  );
}
