FROM node:22-alpine

WORKDIR /app

# Install dependencies first (cache layer)
COPY package.json package-lock.json* ./
RUN npm ci

# Copy source
COPY . .

EXPOSE 3000
CMD ["npm", "run", "dev"]
