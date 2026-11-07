import { Card } from '@/components/card';
import { Detail } from '@/components/detail';
import { Loadable } from '@/components/loadable';
import { MutedText } from '@/components/muted-text';
import { Screen } from '@/components/screen';
import { earningsApi } from '@/crew/api';
import { useLoader } from '@/hooks/use-loader';
import { longDate } from '@/lib/dates';

/**
 * Task D8. What Aveo pays the driver each month, how each month was assessed, and whether it
 * has been paid. Commission only. Fares never pass through Aveo, so they are not here.
 */
export default function EarningsScreen() {
  const state = useLoader(earningsApi.show);

  return (
    <Screen edges={['left', 'right']} refreshing={state.refreshing} onRefresh={() => void state.reload()}>
      <Loadable state={state}>
        {({ monthly_commission_label, months }) => (
          <>
            <Card title="Your monthly commission">
              <Detail label="Aveo pays you each month">{monthly_commission_label}</Detail>
              <MutedText>This is on top of the fares you collect. Fares are yours and never pass through Aveo.</MutedText>
            </Card>

            {months.length === 0 ? (
              <MutedText>No month has been assessed yet. Your first one appears here after month end.</MutedText>
            ) : null}

            {months.map((month) => (
              <Card key={month.month} title={`${month.month} · ${month.total_label}`}>
                <MutedText>
                  {month.paid ? `Paid${month.paid_on ? ` on ${longDate(month.paid_on)}` : ''}.` : 'Not paid yet.'}
                  {month.bonus_label ? ` Includes a bonus of ${month.bonus_label}.` : ''}
                </MutedText>
                <Detail label="Assessment">
                  {month.assessment === 'met' ? 'Met' : month.assessment === 'not_met' ? 'Not met' : 'Not assessed yet'}
                </Detail>
                {month.assessment_notes ? <MutedText>{month.assessment_notes}</MutedText> : null}
              </Card>
            ))}
          </>
        )}
      </Loadable>
    </Screen>
  );
}
