FROM php:8.3-cli
WORKDIR /opt/campus
COPY collector.php setup.php dashboards.php ./
COPY start.sh ./
RUN sed -i 's/\r$//' start.sh && chmod +x start.sh
ENTRYPOINT ["/opt/campus/start.sh"]
