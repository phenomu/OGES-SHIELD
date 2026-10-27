# OGES-SHIELD — orchestrator (FastAPI + AI loop + tool runner)
# Binari ProjectDiscovery diunduh di build stage (bukan dari bin/ host yang
# gitignored) supaya build tetap reproducible di mesin/CI bersih.
FROM debian:bookworm-slim AS gotools

ARG SUBFINDER_VERSION=2.6.7
ARG HTTPX_VERSION=1.7.0
ARG NUCLEI_VERSION=3.8.0
ARG TARGETARCH=amd64

RUN apt-get update \
 && apt-get install -y --no-install-recommends curl unzip ca-certificates \
 && rm -rf /var/lib/apt/lists/*

WORKDIR /out
RUN set -eux; \
    for spec in \
      "subfinder:${SUBFINDER_VERSION}" \
      "httpx:${HTTPX_VERSION}" \
      "nuclei:${NUCLEI_VERSION}"; do \
      name="${spec%%:*}"; ver="${spec#*:}"; \
      curl -fsSL -o "/tmp/${name}.zip" \
        "https://github.com/projectdiscovery/${name}/releases/download/v${ver}/${name}_${ver}_linux_${TARGETARCH}.zip"; \
      unzip -o -j "/tmp/${name}.zip" "${name}" -d /out; \
      chmod +x "/out/${name}"; \
    done; \
    ls -la /out


FROM python:3.11-slim

ENV PYTHONUNBUFFERED=1 \
    PYTHONDONTWRITEBYTECODE=1 \
    PIP_NO_CACHE_DIR=1 \
    PYTHONPATH=/app/orchestrator \
    PATH=/app/bin:$PATH \
    HOME=/home/oges

# libpango/harfbuzz/fontconfig → WeasyPrint (report.pdf)
# fonts-dejavu → font yang dipakai report_template.html
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      libpango-1.0-0 \
      libpangoft2-1.0-0 \
      libharfbuzz0b \
      libfontconfig1 \
      libgdk-pixbuf-2.0-0 \
      libffi8 \
      libjpeg62-turbo \
      libopenjp2-7 \
      libxml2 \
      shared-mime-info \
      fonts-dejavu-core \
      ca-certificates \
      curl \
      git \
 && rm -rf /var/lib/apt/lists/*

# UID/GID 1000 = sama dengan www-data di image web → volume shared bisa ditulis dua-duanya
RUN groupadd -g 1000 oges \
 && useradd -u 1000 -g 1000 -m -s /bin/bash oges

COPY orchestrator/requirements.txt /tmp/requirements.txt
RUN pip install --no-cache-dir -r /tmp/requirements.txt

COPY --from=gotools /out/ /app/bin/

COPY --chown=oges:oges orchestrator/ /app/orchestrator/
COPY docker/orchestrator/entrypoint.sh /usr/local/bin/oges-orchestrator-entrypoint
RUN chmod +x /usr/local/bin/oges-orchestrator-entrypoint

RUN mkdir -p /app/storage/reports /app/storage/evidence \
 && chown -R oges:oges /app/storage /app/orchestrator

USER oges
WORKDIR /app/orchestrator

# Nuclei templates di-bake supaya run pertama tidak menunggu download.
# Gagal (offline) tidak mematikan build — nuclei auto-update saat runtime.
RUN /app/bin/nuclei -update-templates >/dev/null 2>&1 || true

EXPOSE 8100

ENTRYPOINT ["oges-orchestrator-entrypoint"]
