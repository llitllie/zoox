FROM llitllie/swoole:php72-cli

RUN apt-get update \
    && curl -o /tmp/zookeeper-3.4.11.tar.gz https://archive.apache.org/dist/zookeeper/zookeeper-3.4.11/zookeeper-3.4.11.tar.gz -L \
    && tar zxfv /tmp/zookeeper-3.4.11.tar.gz && cd zookeeper-3.4.11/src/c && ./configure && make && make install \
    && docker-php-source extract \
    && pecl install zookeeper \
    && docker-php-ext-enable zookeeper \
    && docker-php-source delete \
    && cd  .. && rm -fr zookeeper-3.4.11 \
    && apt autoremove && apt clean \
    && rm -rf /tmp/* 
