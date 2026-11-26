FROM node:20-alpine

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY . .
RUN chmod +x ./docker-entrypoint.sh

EXPOSE 14800
ENTRYPOINT ["./docker-entrypoint.sh"]
