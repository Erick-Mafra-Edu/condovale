let authenticatedUserId: string | null = null

export const getMockAuthenticatedUserId = () => authenticatedUserId
export const setMockAuthenticatedUserId = (userId: string | null) => { authenticatedUserId = userId }
