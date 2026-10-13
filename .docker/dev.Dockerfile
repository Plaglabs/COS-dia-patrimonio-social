FROM php:8.4-cli-bookworm

ENV TZ=America/Santiago
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

RUN apt-get update -qq && apt-get install -y --no-install-recommends \
  git curl unzip nano default-mysql-client \
  && rm -rf /var/lib/apt/lists/*

# Las mismas extensiones que el ea-php84 del servidor. GD y no imagick: el
# servidor no lo tiene (DOCUMENTACION-TECNICA.md, punto 2).
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql intl gd zip bcmath exif pcntl

# Los límites de subida del servidor, para que una foto que pasa aquí pase allí.
RUN printf 'upload_max_filesize = 8M\npost_max_size = 10M\nmemory_limit = 256M\n' \
  > /usr/local/etc/php/conf.d/dps.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Node sólo hace falta para compilar los assets; en el servidor no existe.
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
  && apt-get install -y --no-install-recommends nodejs \
  && rm -rf /var/lib/apt/lists/*

RUN curl -fsSL https://claude.ai/install.sh | bash
ENV PATH="/root/.local/bin:$PATH" CLAUDE_CONFIG_DIR=/app/.claude/config

WORKDIR /app

CMD ["/app/.docker/init.sh"]
