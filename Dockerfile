FROM wordpress
RUN apt-get update &&\
    apt-get -y install procps libvips libvips-dev

RUN pecl install vips &&\
    docker-php-ext-enable vips

RUN echo "file_uploads = On\n" \
         "memory_limit = 500M\n" \
         "upload_max_filesize = 500M\n" \
         "post_max_size = 500M\n" \
         "max_execution_time = 600\n" \
         > /usr/local/etc/php/conf.d/uploads.ini