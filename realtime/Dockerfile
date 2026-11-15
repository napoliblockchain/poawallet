# wallet-realtime (SIP-029): un solo eseguibile Go statico, immagine minima, utente non root.
FROM golang:1.27.4-alpine3.24 AS build
WORKDIR /src
COPY go.mod go.sum ./
RUN go mod download && go mod verify
COPY . .
RUN CGO_ENABLED=0 go build -trimpath -ldflags="-s -w" -o /out/wallet-realtime .

FROM alpine:3.24.4
RUN adduser -D -H -u 10001 realtime
COPY --from=build /out/wallet-realtime /usr/local/bin/wallet-realtime
USER realtime
HEALTHCHECK --interval=15s --timeout=5s --start-period=30s --retries=4 CMD ["wallet-realtime", "health"]
ENTRYPOINT ["wallet-realtime"]
