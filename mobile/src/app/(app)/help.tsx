import { useState } from 'react';

import { Button } from '@/components/button';
import { Card } from '@/components/card';
import { Choice } from '@/components/choice';
import { ChoiceGroup } from '@/components/choice-group';
import { Loadable } from '@/components/loadable';
import { MessageBubble } from '@/components/message-bubble';
import { MutedText } from '@/components/muted-text';
import { Notice } from '@/components/notice';
import { Screen } from '@/components/screen';
import { SupportButtons } from '@/components/support-buttons';
import { TextField } from '@/components/text-field';
import { useAction } from '@/hooks/use-action';
import { useLoader } from '@/hooks/use-loader';
import { usePoll } from '@/hooks/use-poll';
import { moment } from '@/lib/dates';
import { supportApi, type SupportCase } from '@/support/api';

/**
 * Task S2. Anyone in the app writes to TrustRide here. Her first message says what it is about and
 * opens a case. Staff reply from the console, and the reply shows up here and as a message on her phone.
 */
export default function HelpScreen() {
  const state = useLoader(supportApi.state);
  // What the server said last, after a send or a poll. Until then, what the screen loaded with.
  const [latest, setLatest] = useState<SupportCase | null | undefined>(undefined);
  const [category, setCategory] = useState<string | null>(null);
  const [text, setText] = useState('');
  const send = useAction();

  const current = latest === undefined ? (state.data?.case ?? null) : latest;
  const open = current?.status === 'open';

  usePoll(async () => setLatest((await supportApi.state()).case), open, 10000);

  const submit = () => {
    if (!open && !category) {
      return send.fail('Choose what it is about.');
    }

    if (text.trim() === '') {
      return send.fail('Tell us what happened.');
    }

    void send.run(async () => {
      setLatest(await supportApi.send(text.trim(), open ? null : category));
      setText('');
    });
  };

  return (
    <Screen edges={['bottom']} refreshing={state.refreshing} onRefresh={() => (setLatest(undefined), void state.reload())}>
      <Loadable state={state}>
        {({ categories, support }) => (
          <>
            {current ? (
              <Card title={current.about}>
                <MutedText>
                  {current.status === 'resolved'
                    ? 'We have closed this one. If it is not sorted, write again below.'
                    : current.taken
                      ? 'Someone at TrustRide is on it.'
                      : 'We have your message. Someone will reply soon.'}
                </MutedText>
              </Card>
            ) : (
              <MutedText>Tell us what is wrong. A person at TrustRide reads every message and replies here.</MutedText>
            )}

            {current?.messages.map((message) => (
              <MessageBubble
                key={`${message.at}-${message.from}-${message.body.length}`}
                mine={message.from === 'you'}
                body={message.body}
                caption={`${message.from === 'you' ? 'You' : 'TrustRide'} · ${moment(message.at)}`}
              />
            ))}

            {!open ? (
              <ChoiceGroup title={current ? 'Something new? What is it about?' : 'What is it about?'}>
                {categories.map((option) => (
                  <Choice key={option.key} label={option.label} selected={option.key === category} onPress={() => setCategory(option.key)} />
                ))}
              </ChoiceGroup>
            ) : null}

            <TextField
              label={open ? 'Add a message' : 'What happened?'}
              value={text}
              onChangeText={setText}
              multiline
              maxLength={2000}
              style={{ minHeight: 96, paddingVertical: 12, textAlignVertical: 'top' }}
            />
            <Notice text={send.error} />
            <Button label="Send" loading={send.busy} onPress={submit} />

            <Card title="In a hurry?">
              <MutedText>If you are in danger, call 191 (police) or 112 first. Then tell us.</MutedText>
              <SupportButtons {...support} fallback="Write to us above and we will reply." />
            </Card>
          </>
        )}
      </Loadable>
    </Screen>
  );
}
