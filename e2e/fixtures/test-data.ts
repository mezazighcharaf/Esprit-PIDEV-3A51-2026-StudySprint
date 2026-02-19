export const testUsers = {
  admin: {
    email: 'admin@studysprint.local',
    password: 'admin123',
  },
  user: {
    email: 'alice.martin@studysprint.local',
    password: 'user123',
  },
  user2: {
    email: 'bob.dupont@studysprint.local',
    password: 'user123',
  },
};

export const testQuiz = {
  title: 'Quiz Mathematiques - Integrales',
  difficulty: 'med',
};

export const testDeck = {
  title: 'Derivees et primitives',
};

export const newQuiz = {
  title: 'E2E Test Quiz',
  difficulty: 'easy',
};

export const newQuestion = {
  text: 'What is the capital of France?',
  type: 'mcq',
  points: 2,
};

export const newChoices = [
  { label: 'Paris', correct: true },
  { label: 'London', correct: false },
  { label: 'Berlin', correct: false },
];

export const newDeck = {
  title: 'E2E Test Deck',
};

export const newFlashcard = {
  front: 'Question E2E',
  back: 'Answer E2E',
};
