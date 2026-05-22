FROM python:3.12-slim

ENV PYTHONDONTWRITEBYTECODE=1
ENV PYTHONUNBUFFERED=1

WORKDIR /workspace

# Install scraper dependencies in the image so host Python stays untouched.
COPY scripts/requirements-scraper.txt /tmp/requirements-scraper.txt
RUN pip install --no-cache-dir -r /tmp/requirements-scraper.txt

COPY scripts ./scripts
COPY tests ./tests

ENTRYPOINT ["python", "scripts/scrape_mg_tn.py"]
