import type { FC } from 'react';
import { useTranslation } from 'react-i18next';
import { route } from 'ziggy-js';

import { BaseTableCell, BaseTableRow } from '@/common/components/+vendor/BaseTable';
import { DiffTimestamp } from '@/common/components/DiffTimestamp';
import { InertiaLink } from '@/common/components/InertiaLink';
import { UserAvatar } from '@/common/components/UserAvatar';
import { usePageProps } from '@/common/hooks/usePageProps';
import { cn } from '@/common/utils/cn';
import { formatNumber } from '@/common/utils/l10n/formatNumber';

interface MessagesTableRowProps {
  messageThread: App.Community.Data.MessageThread;
}

export const MessagesTableRow: FC<MessagesTableRowProps> = ({ messageThread }) => {
  const { auth, senderUserDisplayName } =
    usePageProps<App.Community.Data.MessageThreadIndexPageProps>();

  const { t } = useTranslation();

  // Find who we're chatting with in order to populate the "With" column.
  const otherParticipant =
    (messageThread.participants?.find(
      (p) => p.displayName !== senderUserDisplayName,
    ) as App.Data.User) ?? messageThread.participants?.[0];

  return (
    <BaseTableRow className={cn(messageThread.isUnread ? 'font-bold' : null)}>
      <BaseTableCell>
        <InertiaLink href={route('message-thread.show', messageThread.id)}>
          {messageThread.title}
        </InertiaLink>
      </BaseTableCell>

      <BaseTableCell>
        <UserAvatar {...otherParticipant} size={24} />
      </BaseTableCell>

      <BaseTableCell className="text-right">
        {formatNumber(messageThread.numMessages)} {messageThread.isUnread ? t('(unread)') : null}
      </BaseTableCell>

      <BaseTableCell className="text-right">
        <DiffTimestamp
          asAbsoluteDate={auth?.user.preferences.prefersAbsoluteDates ?? false}
          at={messageThread.lastMessage!.createdAt}
          className="text-2xs text-neutral-400 light:text-neutral-800"
        />
      </BaseTableCell>
    </BaseTableRow>
  );
};
