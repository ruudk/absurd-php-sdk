FROM alpine:3.19

ARG ABSURD_VERSION=0.0.7
ARG TARGETARCH

RUN apk add --no-cache curl ca-certificates

# Map Docker's TARGETARCH to habitat binary names
RUN case "${TARGETARCH}" in \
        amd64) ARCH="x86_64" ;; \
        arm64) ARCH="arm64" ;; \
        *) echo "Unsupported architecture: ${TARGETARCH}" && exit 1 ;; \
    esac && \
    curl -fsSL "https://github.com/earendil-works/absurd/releases/download/${ABSURD_VERSION}/habitat-linux-${ARCH}" \
        -o /usr/local/bin/habitat && \
    chmod +x /usr/local/bin/habitat

EXPOSE 7890

ENTRYPOINT ["habitat", "run"]
