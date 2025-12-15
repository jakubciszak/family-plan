# Development Dockerfile for React frontend
FROM node:20-alpine

WORKDIR /app

# Install dependencies
RUN apk add --no-cache \
    git \
    bash

# Copy package files
COPY package*.json ./

# Install npm dependencies
RUN npm install

# Copy application code
COPY . .

# Expose port for webpack dev server (if needed in future)
EXPOSE 3000

# Default command - watch mode for development
CMD ["npm", "run", "watch"]
