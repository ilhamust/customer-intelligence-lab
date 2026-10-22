FROM node:22-alpine

WORKDIR /app

# Salin manifest dependensi terlebih dahulu untuk optimasi Docker layer caching
COPY package*.json ./

# Install dependensi
RUN npm install

# Salin sisa file aplikasi
COPY . .

EXPOSE 5173

CMD ["npm", "run", "dev"]
