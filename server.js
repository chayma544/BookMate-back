import express from 'express';
import cors from 'cors';

const app = express();
const PORT = 3000;

// Middleware
app.use(cors());
app.use(express.json());

// Test routes

// Basic Routes
app.get('/', (req, res) => {
  res.send('Welcome to BookMate API');
});

// Books Routes
app.get('/api/books', (req, res) => {
  res.json([{ id: 1, title: 'Sample Book' }]);
});

app.post('/api/books', (req, res) => {
  const newBook = req.body;
  // Save to database later
  res.status(201).json(newBook);
});

// Start server
app.listen(PORT, () => {
  console.log(`Server running on http://localhost:${PORT}`);
});