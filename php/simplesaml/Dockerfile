FROM ubuntu:latest
LABEL maintainer="mukunda@mukunda.com"

# Packages we want. Include nano for fiddling.
RUN apt-get update && apt-get install -y \
   wget \
   nginx \
   php7.4-fpm \
   php7.4-cli \
   php7.4-common \
   php-dom \
   nano

COPY . /

# Do setup and then tidy up.
RUN /setup.sh && rm -rf /var/lib/apt/lists

ENTRYPOINT ["/start.sh", "/bin/bash"]
