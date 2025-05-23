import type { FC } from 'react';
import { Trans } from 'react-i18next';
import { route } from 'ziggy-js';

import { UserAvatar } from '@/common/components/UserAvatar';
import { usePageProps } from '@/common/hooks/usePageProps';

import { DiffTimestamp } from '../DiffTimestamp';
import { InertiaLink } from '../InertiaLink';
import { RecentPostAggregateLinks } from '../RecentPostAggregateLinks';

interface RecentPostsCardsProps {
  paginatedTopics: App.Data.PaginatedData<App.Data.ForumTopic>;

  showUser?: boolean;
}

export const RecentPostsCards: FC<RecentPostsCardsProps> = ({
  paginatedTopics,
  showUser = true,
}) => {
  const { auth } = usePageProps<App.Community.Data.RecentPostsPageProps>();

  return (
    <div className="flex flex-col gap-y-2">
      {paginatedTopics.items.map((topic) => (
        <div key={`card-${topic?.latestComment?.id}`} className="embedded">
          <div className="relative flex justify-between">
            <div className="flex flex-col gap-y-1">
              {showUser && topic.latestComment?.user ? (
                <UserAvatar {...topic.latestComment.user} size={16} />
              ) : null}

              {topic.latestComment?.createdAt ? (
                <span className="smalldate" data-testid="timestamp">
                  <DiffTimestamp
                    asAbsoluteDate={auth?.user.preferences.prefersAbsoluteDates ?? false}
                    at={topic.latestComment.createdAt}
                  />
                </span>
              ) : null}
            </div>

            <RecentPostAggregateLinks topic={topic} />
          </div>

          <div className="flex flex-col gap-y-2">
            <p className="truncate">
              <Trans
                i18nKey="in <1>{{forumTopicTitle}}</1>"
                values={{ forumTopicTitle: topic.title }}
                components={{
                  1: (
                    <InertiaLink
                      href={
                        route('forum-topic.show', {
                          topic: topic.id,
                          comment: topic.latestComment?.id,
                        }) + `#${topic.latestComment?.id}`
                      }
                    />
                  ),
                }}
              />
            </p>

            <p className="line-clamp-3 text-xs">{topic.latestComment?.body}</p>
          </div>
        </div>
      ))}
    </div>
  );
};
