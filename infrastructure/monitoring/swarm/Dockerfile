FROM python:3.12-slim
WORKDIR /opt/campus
COPY collector.py metrics.py ./
ENV PYTHONUNBUFFERED=1 PYTHONDONTWRITEBYTECODE=1
CMD ["python", "collector.py"]
