ARG NEXTCLOUD_VERSION=34.0.5
FROM nextcloud:${NEXTCLOUD_VERSION}-apache

COPY --chmod=755 hooks/before-starting/ /docker-entrypoint-hooks.d/before-starting/
COPY --chmod=755 hooks/repair.sh /docker-entrypoint-hooks.d/post-installation/10-repair.sh
COPY --chmod=755 hooks/repair.sh /docker-entrypoint-hooks.d/post-upgrade/10-repair.sh

COPY --chmod=755 hooks/pre-installation/ /docker-entrypoint-hooks.d/pre-installation/
COPY prepare-db.php /usr/local/lib/campus/prepare-db.php

RUN sed -i 's/\r$//' /docker-entrypoint-hooks.d/*/*.sh
