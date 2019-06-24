FROM llitllie/swoole:php72-cli-alpine

RUN set -ex \
  	&& apk update \
    && apk add --no-cache --virtual .build-deps curl gcc g++ make build-base autoconf \
    && curl -o /tmp/zookeeper-3.4.11.tar.gz https://archive.apache.org/dist/zookeeper/zookeeper-3.4.11/zookeeper-3.4.11.tar.gz -L \
    && tar zxfv /tmp/zookeeper-3.4.11.tar.gz && cd zookeeper-3.4.11/src/c && ./configure && make && make install \
    && docker-php-source extract \
    && pecl install zookeeper \
    && docker-php-ext-enable zookeeper \
    && docker-php-source delete \
    && cd  .. && rm -fr zookeeper-3.4.11 \
    && apk del .build-deps \
    && rm -rf /tmp/* 

ENV ZOOKEEPER_CONNECTION "192.168.33.1:2181,192.168.33.1:2182,192.168.33.1:2183"
EXPOSE 9502